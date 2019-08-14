# Data for mybb_predictions
The predictions plugin requires the following data (`and their db tables`):
* Conference `<db_prefex>_predictions_conference`
* Team `<db_prefix>_predictions_team`
* Game `<db_prefix>_predictions_game`
* Prediction `<db_prefix>_predictions_prediction`

The initial set of teams can be found in `teams.json`.  This includes the abbreviation of each team and the path to the team logo.

For ongoing game data, we leverage the [College Football Data API](https://api.collegefootballdata.com).

## Initial data
We generate the initial set of conference and team data using the `get_conferences_and_teams.py` script.  This will update the `../Upload/admin/modules/predictions/mysql_db_inserts.php`.  This script will populate the appropriate tables with the conference and team data when the mybb admin user installs the plugin.

## Ongoing game data


## Deprecated
The `cardboard` folder contains historical files used for importing predictions manually.  

