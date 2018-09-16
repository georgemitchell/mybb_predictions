import csv

def get_users(filename):
    users = {}
    with open(filename, 'rb') as csvfile:
        reader = csv.reader(csvfile)
        for row in reader:
            uid = int(row[0])
            username = row[1].strip().lower()
            users[username] = uid
    return users

def import_scores(filename, users, game_id, output, skip_ids=set([])):
    """names = users.keys()
    names.sort()
    for name in names:
        print name
    """
    f = open(filename, "r")
    line = f.readline()
    while line != "":
        cols = line.split("\t")
        score = cols[1].strip().split("-")
        home_score = int(score[0])
        away_score = int(score[1])
        username = cols[0].strip().lower()
        if username not in users:
            print "Couldn't find: >%s<" % username
        else:
            user_id = users[username]
            if user_id in skip_ids:
                print "Skipping %s" % username
            else:
                sql = "insert into mybb_predictions_prediction(game_id, user_id, home_score, away_score, timestamp) values (%d, %d, %d, %d, '2018-09-01 00:00:00');\n" % (game_id, user_id, home_score, away_score)
                output.write(sql)
        line = f.readline()



users = get_users("mybb_users.csv")
output_file = open("import_games.sql", "w")
import_scores("sdsu.txt", users, 28, output_file)
import_scores("usc.txt", users, 29, output_file, [1, 60, 194, 213])
import_scores("ucd.txt", users, 30, output_file, [60, 194, 213])
output_file.close()
