'''
This is super annoying because I don't have the necessary permissions
to run queries on the MySQL db remotely:

mysql: [Warning] Using a password on the command line interface can be insecure.
ERROR 1045 (28000): Access denied for user 'xxxx'@'47.151.152.232' (using password: YES)

So I have to use the myphpadmin "export" functionality.  There are too many predictions 
to do this dynamically so for that data I need to create a temp table.

create table temp_export
select u.uid, u.username, g.game_id, g.home_school, g.away_school, g.home_score as actual_home_score, g.away_score as actual_away_score, p.home_score, p.home_nickname, p.away_score, p.away_nickname, p.points, p.timestamp
from cardbd_predictions_game g 
inner join cardbd_predictions_prediction p on g.game_id = p.game_id
inner join cardbd_users u on p.user_id = u.uid
where g.season = 2022;

Then use the export functionality to grab these results as a csv.

Finally, drop the temp table.

'''

import pandas

def get_data(filename):
    data = pandas.read_csv(filename)
    return data

def get_winners(data):
    point_totals = data[['username', 'points']]
    winners = point_totals.groupby("username").sum().sort_values(by=['points'], ascending=False).head()
    print("Winners")
    print("-------")
    print(winners)

def get_num_player_stats(data):
    players = data[['username']].groupby("username")
    print(players)

if __name__ == "__main__":
    data = get_data("2022_results.csv")
    get_winners(data)
    get_num_player_stats(data)

