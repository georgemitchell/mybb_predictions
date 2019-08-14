import json
import random
import time
import os
from api import CollegeFootballAPI

def validate_conference_coverage(json_conferences, api_conferences):
    new_id = 500
    missing = []
    for conference in json_conferences:
        if conference not in api_conferences:
            print("Error, unable to find {} conference".format(conference))
            missing.append((new_id, conference))
            new_id += 1
    return missing

def merge(json_data, output_file):
    api = CollegeFootballAPI();
    conferences = api.get_conferences()
    conference_lookups = {}
    for conference in conferences:
        for key in ["name", "short_name", "abbreviation"]:
            if conference[key] in conference_lookups:
                if conference_lookups[conference[key]] != conference["id"]:
                    if conference[key] != "Missouri Valley":
                        print("Collision: {} appears twice!".format(conference[key]))
                        return
                    else:
                        # Missouri Valley appears twice in the api with slightly different full names
                        # this would be a problem, but it turns out that there aren't any teams mapped
                        # to either version, so this is a non-issue (but still annoying, because we need 
                        # to manually add ids for these teams)
                        continue
            else:
                conference_lookups[conference[key]] = conference["id"]

    json_conferences = json_data.keys()
    
    f = open(output_file, "w")
    f.write("<?php\n")
    for conference in conferences:
        f.write("$inserts[] = \"INSERT INTO mybb_predictions_conference (conference_id, name) values ({}, '{}')\";\n".format(conference["id"], conference["abbreviation"]))

    missing = validate_conference_coverage(json_conferences, conference_lookups)
    for id, conference in missing:
        conference_lookups[conference] = id
        f.write("$inserts[] = \"INSERT INTO mybb_predictions_conference (conference_id, name) values ({}, '{}')\";\n".format(id, conference))
        
    confirmed_team_set = set([])
    for conference in conferences:
        print("Retrieving teams for {}".format(conference["name"]))
        teams = api.get_teams(conference["abbreviation"])
        for team in teams:
            if team["conference"] not in json_data:
                print("unable to locate conference {} in json_data".format(team["conference"]))
                continue
            else:
                if team["school"] not in json_data[team["conference"]]:
                    print("unable to locate team {} in json_data for {}".format(team["school"], team["conference"]))
                    continue
                else:
                    json_team = json_data[team["conference"]][team["school"]]
                    f.write("$inserts[] = \"INSERT INTO mybb_predictions_team (school, conference_id, abbreviation, mascot, division, color, alt_color, logo) values ('{school}', {conference_id}, '{abbreviation}', '{mascot}', '{division}', '{color}', '{alt_color}', '{logo}')\";\n".format(
                        school=team["school"],
                        conference_id=conference_lookups[team["conference"]],
                        abbreviation=team["abbreviation"],
                        mascot=team["mascot"],
                        division=team["division"],
                        color=team["color"],
                        alt_color=team["alt_color"],
                        logo="/images/predictions/logos/{}".format(os.path.basename(json_team["pic"]))
                    ))
                    confirmed_team_set.add(team["school"])
        throttle = random.randint(4,8)
        print("Pausing for {} seconds...".format(throttle))
        time.sleep(throttle)
        #break

    all_teams = {}
    for conference in json_data:
        for team in json_data[conference]:
            if team not in confirmed_team_set:
                print("Adding {} manually".format(team))
                json_team = json_data[conference][team]
                f.write("$inserts[] = \"INSERT INTO mybb_predictions_team (school, conference_id, abbreviation, mascot, logo) values ('{school}', {conference_id}, '{abbreviation}', '{mascot}', '{logo}')\";\n".format(
                        school=team,
                        conference_id=conference_lookups[conference],
                        abbreviation=json_team["abbrev"],
                        mascot=json_team["mascot"],
                        logo="/images/predictions/logos/{}".format(os.path.basename(json_team["pic"]))
                    )
                )
    
    f.close()


if __name__ == "__main__":
    f = open("teams.json", "r")
    json_data = json.load(f)
    f.close()
    merge(json_data, "../Upload/admin/modules/predictions/mysql_db_inserts.php")

