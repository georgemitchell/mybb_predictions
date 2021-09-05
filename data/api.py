import requests
import json
class CollegeFootballAPIError(BaseException):
    def __init__(self, message):
        self.message = message
class CollegeFootballAPI(object):
    BASE_URI = "https://api.collegefootballdata.com/"

    def __init__(self):
        try:
            from config import stats_key
            self.stats_key = stats_key
        except ModuleNotFoundError:
            print("Couldn't find config.py containing stats_key, does it need to be created?")
            self.stats_key = None
        pass

    def query(self, url):
        if self.stats_key is None:
            raise CollegeFootballAPIError("No stats_key defined, unable to run query")
        print("Retrieving {}".format(url))
        headers = {"Authorization": "Bearer {}".format(self.stats_key)}
        response = requests.get(url, headers=headers)
        if response.status_code == 200:
            data = response.json()
            return data
        else:
            raise CollegeFootballAPIError("GET {} {}".format(url, response.status_code))

    def get_conferences(self):
        url = "{}conferences".format(self.BASE_URI)
        return self.query(url)

    def get_teams(self, conference):
        url = "{}teams?conference={}".format(self.BASE_URI, conference)
        return self.query(url)

    def get_games(self, team, year=2019, season_type="regular"):
        url = "{}games?year={}&seasonType={}&team={}".format(self.BASE_URI, year, season_type, team)
        return self.query(url)

if __name__ == "__main__":
    team = "Stanford"
    year = 2021
    season_type = "regular"
    api = CollegeFootballAPI()
    games = api.get_games(team, year, season_type)
    print(games)
    