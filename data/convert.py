import codecs
import json
import re
from datetime import datetime, timedelta
import os.path

def parse_original_games_row(row):
    tokens = line.strip().split("|")
    if len(tokens) == 4:
        date = tokens[0]
        location = tokens[1]
        school = tokens[2]
        time = tokens[3]
        if school not in teams:
            print("can't find {}".format(school))
        else:
            if location == "@":
                home_tid = teams[school]
                away_tid = teams["Stanford"]
            else:
                home_tid = teams["Stanford"]
                away_tid = teams[school]
            if time == "TBD":
                dt_str = "{}, 2018 8:00 PM ET".format(date)
            else:
                dt_str = "{}, 2018 {}".format(date, time)
            dt = datetime.strptime(dt_str, "%a, %b %d, %Y %I:%M %p ET")
            if previous_start == None:
                start = dt - timedelta(weeks=1)
            else:
                start = previous_start + timedelta(days=1)
    
SCHOOL_RE = re.compile("(?:[(][0-9]+[)] )?(.+)")
def parse_common_games_row(row, teams, previous_start, previous_season):
    tokens = row.split("\t")
    if len(tokens) != 7:
        print("Malformed row: {}".format(tokens))
        return None
    else:
        date_str = tokens[0]
        time_str = tokens[1]
        location = tokens[2]
        school = tokens[3]
        match = SCHOOL_RE.match(school)
        if match == None:
            print("Unable to match school: {}".format(school))
            return None
        else:
            school = match.group(1)
        
        if school not in teams:
            print("Unable to find school: {}".format(school))
            return None
            
        result = tokens[4].strip()
        if result != "":
            stanford_pts = tokens[5]
            opp_pts = tokens[6]
        else:
            stanford_pts = None
            opp_pts = None
        dt = datetime.strptime("{} {}".format(date_str, time_str), "%d-%b-%y %I:%M %p")
        if location == "@":
            home_tid = teams[school]
            away_tid = teams["Stanford"]
            home_score = opp_pts
            away_score = stanford_pts
        else:
            home_tid = teams["Stanford"]
            away_tid = teams[school]
            home_score = stanford_pts
            away_score = opp_pts
        
        season = dt.year
        if previous_start == None:
            start = dt - timedelta(weeks=1)
        else:
            if season == previous_season:
                start = previous_start + timedelta(days=1)
            else:
                start = dt - timedelta(weeks=1)
        output = {
            "school": school,
            "prediction_time": start.strftime("%Y-%m-%d %H:%M:%S"),
            "game_time": dt.strftime("%Y-%m-%d %H:%M:%S"),
            "season": season,
            "home_tid": home_tid,
            "away_tid": away_tid,
            "home_score": home_score,
            "away_score": away_score,
            "next_previous": dt + timedelta(days=1)
        }
        return output


def write_teams_data(data, fp):
    teams = {}
    tid = 0
    for counter, conference in enumerate(data):
        cid = counter + 1
        fp.write("$inserts[] = \"INSERT INTO mybb_predictions_conference (conference_id, name) values ({}, '{}')\";\n".format(cid, conference.replace("'", "''")))
        for name in data[conference]:
            tid += 1
            team = data[conference][name]
            teams[name] = tid
            logo = "/images/predictions/logos/{}".format(os.path.basename(team["pic"]))
            fp.write(u"$inserts[] = \"INSERT INTO mybb_predictions_team (team_id, conference_id, name, abbreviation, mascot, logo, color) values ({}, {}, '{}', '{}', '{}', '{}', '#000000')\";\n".format(tid, cid, name, team["abbrev"], team["mascot"], logo))
    return teams

def write_games_data(teams, games_filename, fp):
    gid = 1
    schedule = open(games_filename, "r")
    previous_start = None
    previous_season = None
    for line in schedule:
        row = parse_common_games_row(line, teams, previous_start, previous_season)
        if row is not None:
            row["gid"] = gid
            if row["home_score"] is not None:
                insert = "INSERT INTO mybb_predictions_game(game_id, season, home_team_id, away_team_id, prediction_time, game_time, home_score, away_score) values ({gid}, {season}, {home_tid}, {away_tid}, '{prediction_time}', '{game_time}', {home_score}, {away_score})".format(**row)
            else:
                insert = "INSERT INTO mybb_predictions_game(game_id, season, home_team_id, away_team_id, prediction_time, game_time) values ({gid}, {season}, {home_tid}, {away_tid}, '{prediction_time}', '{game_time}')".format(**row)
            fp.write(u"$inserts[] = \"{}\";\n".format(insert))
            previous_start = row["next_previous"]
            previous_season = row["season"]
            gid += 1


def parse_data(teams_filename, games_filename):
    fp = open(teams_filename, "r")
    data = json.load(fp)
    fp.close()

    fp = codecs.open("../Upload/admin/modules/predictions/mysql_db_inserts.php", "w", "utf-8")

    fp.write("<?php\n")
    teams = write_teams_data(data, fp)
    write_games_data(teams, games_filename, fp)

    fp.close()


if __name__ == "__main__":
    parse_data("teams.json", "2016-2018.txt")
