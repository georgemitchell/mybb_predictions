<?php

// Set some useful constants that the core may require or use
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'predictions.php');

// Including global.php gives us access to a bunch of MyBB functions and variables
require_once "./global.php";

$lang->load('predictions');

// Only required because we're using misc_help for our page wrapper
$lang->load("misc");

class Score {
    var $delta;
    var $num_exact;
    var $largest_margin;
    var $picked_winner;
    var $prediction_id;
    var $points;

    function __construct($prediction_id, $home, $away, $home_actual, $away_actual) {
        $this->prediction_id = $prediction_id;
        $this->num_exact = 0;
        $home_margin = abs($home_actual - $home);
        $away_margin = abs($away_actual - $away);
        if($home_actual > $away_actual) {
            $this->picked_winner = ($home > $away);
        } else {
            $this->picked_winner = ($away > $home);
        }
        $this->delta = $home_margin + $away_margin;
        if($home_margin == 0) {
            $this->num_exact++;
        }
        if($away_margin == 0) {
            $this->num_exact++;
        }
        $this->largest_margin = ($home_margin > $away_margin) ? $home_margin : $away_margin;
        $this->points = null;
    }

    function compare($other) {
        if($this->picked_winner && $other->picked_winner) {
            if($this->delta == $other->delta) {
                if($this->num_exact == $other->num_exact) {
                    return 0;
                } else {
                    return ($this->num_exact > $other->num_exact) ? -1 : 1;
                }
            } else {
                return ($this->delta < $other->delta) ? -1 : 1;
            }
        } else if($this->picked_winner) {
            return -1;
        } else {
            return 1;
        }
    }
}

function score_compare($a, $b) {
    return $a->compare($b);
}

function calculate_points($game_id, $away_actual, $home_actual, $did_stanford_win) {
    global $db;

    $scores = array();
    $query = $db->simple_select("predictions_prediction", "prediction_id, game_id, home_score, away_score", "game_id=".$game_id);
    while($prediction = $db->fetch_array($query)) {
        $score = new Score(
            $prediction["prediction_id"],
            $prediction['home_score'],
            $prediction['away_score'],
            $home_actual, 
            $away_actual
        );
        array_push($scores, $score);
    }

    usort($scores, "score_compare");
    // Total number of points are calcuated by taking the number of scores 
    $current_points = count($scores);
    $previous = null;
    $decrement = 1;
    foreach($scores as &$score) {
        if(!is_null($previous)) {
            if($score->compare($previous) == 0) {
                $decrement++;
            } else {
                $current_points -= $decrement;
                $decrement = 1;
            }
        }
        $score->points = $current_points;
        $previous = $score;
    }

    // Update the DB with the scores
    foreach($scores as &$score) {
        if($score->num_exact >= 2) {
            $score->points += 10;
        } else if($score->num_exact == 1) {
            $score->points += 3;
        }
        if(($score->picked_winner && !$did_stanford_win) || (!$score->picked_winner && $did_stanford_win)) {
            $score->points -= 10;
        }
        $db->update_query("predictions_prediction", array("points" => $score->points), "prediction_id=".$score->prediction_id);
    }
    
}
// Add a breadcrumb
add_breadcrumb('Predictions', "predictions.php");

$game_id=$mybb->get_input('game_id');

if($mybb->get_input('action') == 'predictions_update_actual') {
    if($mybb->request_method == 'post') {
        verify_post_check($mybb->get_input('csrf_token'));
        $args = array(
			'game_id' => $game_id,
			'home_score' => $mybb->get_input('home_actual'),
			'away_score' => $mybb->get_input('away_actual')
        );
        $db->update_query('predictions_game', $args, "game_id=".$game_id);
        $did_stanford_win = ($mybb->get_input('team_is_home')) ? $args['home_score'] > $args['away_score'] : $args['home_score'] < $args['away_score'];
        calculate_points($game_id, $args['away_score'], $args['home_score'], $did_stanford_win);
    }
}

// Retreive eligible games
$query = $db->query("
    SELECT g.game_id, a.name as away_name, h.name as home_name, g.game_time, g.away_score, g.home_score
    FROM ".TABLE_PREFIX."predictions_game g
    INNER JOIN ".TABLE_PREFIX."predictions_team a ON (a.team_id=g.away_team_id)
    INNER JOIN ".TABLE_PREFIX."predictions_team h ON (h.team_id=g.home_team_id)
    WHERE g.thread_id IS NOT NULL AND g.season=2019
    ORDER BY g.game_time ASC
");
$predictions_game_options = "";
while($row = $db->fetch_array($query)) {
    if($row['game_id'] == $game_id) {
        $predictions_game_options .= '<option value="'.$row["game_id"].'" selected>'.$row["away_name"].' at '.$row["home_name"].'</option>';
    } else {
        $predictions_game_options .= '<option value="'.$row["game_id"].'">'.$row["away_name"].' at '.$row["home_name"].'</option>';
    }
}

$predictions_game_results="";
$predictions_update_actual_score = "";
$overall_standings_link = "";
if($game_id != "") {
    $overall_standings_link = "<a href=\"{$mybb->settings['bburl']}/predictions.php\" class=\"button\">See Overall Standings</a>";
    $query = $db->query("
        SELECT u.username, p.timestamp, p.points, p.away_score, p.home_score, p.away_nickname, p.home_nickname, a.abbreviation as away_team, h.abbreviation as home_team, g.home_team_id, g.home_score as home_actual, g.away_score as away_actual
        FROM ".TABLE_PREFIX."predictions_prediction p
        INNER JOIN ".TABLE_PREFIX."predictions_game g ON p.game_id = g.game_id
        INNER JOIN ".TABLE_PREFIX."predictions_team a ON (a.team_id=g.away_team_id)
        INNER JOIN ".TABLE_PREFIX."predictions_team h ON (h.team_id=g.home_team_id)
        INNER JOIN ".TABLE_PREFIX."users u ON (p.user_id = u.uid)
        WHERE g.game_id=".$game_id."
        ORDER BY p.points desc, p.timestamp
    ");
    $first = true;
    $home_team = 'Home';
    $away_team = 'Away';
    $predictions_predictions_results = "";
    $stanford_id = 151;
    $predictions_results_columns = null;
    while($row = $db->fetch_array($query)) {
        $home_team = $row['home_team'];
        $away_team = $row['away_team'];
        if($first) {
            $home_actual = $row['home_actual'];
            $away_actual = $row['away_actual'];
            $team_is_home = $row['home_team_id'] == $stanford_id;
            if($mybb->user['ismoderator']) {
                eval('$predictions_update_actual_score = "' . $templates->get('predictions_update_actual_score') . '";');
            }
            $first = false;
        }
        $prediction = array($row['username'], null, null, $row["timestamp"]);
        if(!is_null($row['home_nickname'])) {
            $home_team = $row['home_nickname'];
        }
        if(!is_null($row['away_nickname'])) {
            $away_team = $row['away_nickname'];
        }
        $prediction[1] = $away_team . " " . $row['away_score'] . ", " . $home_team . " " . $row['home_score'];

        if(is_null($row["points"])) {
            $prediction[2] = "-";
        } else {
            $prediction[2] = $row['points'];
        }

        $predictions_results_columns = array(
            "Username",
            "Prediction",
            "Points",
            "Timestamp"
        );
        eval('$predictions_predictions_results .= "'. $templates->get('predictions_row') .'";');
    }
} else {
    $query = $db->query("
        SELECT u.username, SUM(p.points) as points, COUNT(p.prediction_id) as num_games
        FROM ".TABLE_PREFIX."predictions_prediction p
        INNER JOIN ".TABLE_PREFIX."predictions_game g ON p.game_id = g.game_id
        INNER JOIN ".TABLE_PREFIX."users u ON (p.user_id = u.uid)
        WHERE g.season=2019
        GROUP BY username
        ORDER BY points desc, num_games desc, username
    ");
    $predictions_results_columns = array(
        "Username",
        "Total Points",
        "Num Games Played"
    );
    while($row = $db->fetch_array($query)) {
        $prediction = array($row['username'], $row['points'], $row['num_games']);
        eval('$predictions_predictions_results .= "'. $templates->get('predictions_row') .'";');
    }
}
eval('$predictions_game_results = "' . $templates->get('predictions_list') . '";');

$predictions_results = $templates->get('predictions_index');

eval('$sections  = "' . $predictions_results . '";');

// Using the misc_help template for the page wrapper
eval("\$page = \"".$templates->get("misc_help")."\";");

// Spit out the page to the user once we've put all the templates and vars together
output_page($page);


?>