# mybb_predictions
A project to allow a game predictions module for MyBB, inspired by [Predictions on the CARDBoard](https://thecardboard.org/board/showthread.php?tid=16302https://thecardboard.org/board/showthread.php?tid=16302).

This module will allow you to add a prediciton to a thread similar to adding Polls.  The list of available games comes from the database.  Each game is loaded with the game_time as well as a prediction_time that specifies the time after which predictions can be made.

![New Prediction](Documentation/screenshots/new_thread.png?raw=true)

Once a prediction thread is created, the current status of the game prediction will be shown at the top of the thread:

![Thread View](Documentation/screenshots/thread_view.png?raw=true)

Logged in users will be able to make / update their predictions up until the game start time (as defined in the database).

![Thread View](Documentation/screenshots/make_prediction.png?raw=true)

The up-to-date results of the currently pending game is shown at the top of the forum page. Threads containing predictions have the current results appended to their title automatically.

![Thread View](Documentation/screenshots/live_results.png?raw=true)

The full results for any game can be viewed both during the prediction period, and after (once points are assigned.) Points are assigned automatically when a moderator updates the actual score on this same screen.

![Thread View](Documentation/screenshots/results.png?raw=true)


