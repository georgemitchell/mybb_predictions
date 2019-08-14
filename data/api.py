import requests
import json

class CollegeFootballAPIError(object):
    def __init__(self, message):
        self.message = message


class CollegeFootballAPI(object):
    BASE_URI = "https://api.collegefootballdata.com/"

    def __init__(self):
        pass

    def query(self, url):
        print("Retrieving {}".format(url))
        response = requests.get(url)
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
