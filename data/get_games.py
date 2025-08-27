from datetime import datetime, timedelta
from dateutil import tz
from api import CollegeFootballAPI

def convert_time(time_str):
    from_zone = tz.gettz('UTC')
    to_zone = tz.gettz('America/New_York')
    dt = datetime.strptime(time_str, "%Y-%m-%dT%H:%M:%S.%fZ")
    utc = dt.replace(tzinfo=from_zone)
    eastern = utc.astimezone(to_zone)
    return eastern

if __name__ == "__main__":
    year = 2025
    team = "Stanford"
    season_type = "regular"
    prefix = "cardbd"
    api = CollegeFootballAPI()

    output_filename = "data_imports/{}_{}_{}.sql".format(team, year, season_type)
    f = open(output_filename, "w")
    games = api.get_games(team, year, season_type)
    prediction_time = datetime.now()
    print(games)
    for game in games:
        if game["homePoints"] is None:
            home_score = "NULL"
        else:
            home_score = game["homePoints"]

        if game["awayPoints"] is None:
            away_score = "NULL"
        else:
            away_score = game["awayPoints"]

        game_time = convert_time(game["startDate"])
        f.write("INSERT INTO {prefix}_predictions_game(game_id, season, home_school, away_school, prediction_time, game_time, home_score, away_score) values ({game_id}, {season}, '{home_school}', '{away_school}', '{prediction_time}', '{game_time}', {home_score}, {away_score}) on duplicate key update game_time='{game_time}', home_score={home_score}, away_score={away_score};\n".format(
            prefix = prefix,
            game_id = game["id"],
            season = game["season"],
            home_school = game["homeTeam"],
            away_school = game["awayTeam"],
            prediction_time = prediction_time.strftime("%Y-%m-%d %H:%M:%S"),
            game_time = game_time.strftime("%Y-%m-%d %H:%M:%S"),
            home_score = home_score,
            away_score = away_score
        ))
        prediction_time = game_time + timedelta(days=1)
    f.close()


