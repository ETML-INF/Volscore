<div style='font-family:Arial, Helvetica, sans-serif'>
<?php

// reset db
$command = file_get_contents('../../../Database/volscore.sql');
VolscoreDB::executeUpdateQuery($command);


// Add some games
$today = date("Y-m-d");
$rec = rand(1,6);
$vis = rand(1,6);
$query =
"INSERT INTO games (type,level,category,league,location,venue,moment,receiving_id,visiting_id) ".
"VALUES('Coupe', 'Régional-Vaud', 'F', 'F2', 'Froideville', 'Complexe sportif', '$today 20:00', $rec, $vis);";
$newgame = VolscoreDB::executeInsertQuery($query);

$tomorrow = date('Y-m-d', strtotime($today. ' + 1 days'));
$rec = rand(1,6);
$vis = rand(1,6);
$query =
"INSERT INTO games (type,level,category,league,location,venue,moment,receiving_id,visiting_id) ".
"VALUES('Coupe', 'Régional-Vaud', 'F', 'F2', 'Lausanne', 'Vennes', '$tomorrow 21:00', $rec, $vis);";
$newgame = VolscoreDB::executeInsertQuery($query);

$aftertomorrow = date('Y-m-d', strtotime($today. ' + 2 days'));
$rec = rand(1,6);
$vis = rand(1,6);
$query =
"INSERT INTO games (type,level,category,league,location,venue,moment,receiving_id,visiting_id) ".
"VALUES('Coupe', 'Régional-Vaud', 'F', 'F2', 'Lutry', 'Les Pales', '$aftertomorrow 20:45', $rec, $vis);";
$newgame = VolscoreDB::executeInsertQuery($query);

// Add scores to games in the past
// Start with listing past games
$dbh = VolscoreDB::connexionDB();
$pastgames = array();
$today = date("Y-m-d");
$query = "SELECT games.id, type, level,category,league,receiving_id,r.name as receiving,visiting_id,v.name as visiting,location,venue,moment " .
         "FROM games INNER JOIN teams r ON games.receiving_id = r.id INNER JOIN teams v ON games.visiting_id = v.id " .
         "WHERE moment < '$today'";
$statement = $dbh->prepare($query); // Prepare query
$statement->execute(); // Executer la query
$dbh = null;

while ($row = $statement->fetch()) {
    $newgame = new Game(
        [
            "number" => $row["id"],
            "type" => $row["type"],
            "level" => $row["level"],
            "category" => $row["category"],
            "league" => $row["league"],
            "receiving_id" => $row["receiving_id"],
            "receiving" => $row["receiving"],
            "visiting_id" => $row["visiting_id"],
            "visiting" => $row["visiting"],
            "location" => $row["location"],
            "venue" => $row["venue"],
            "moment" => $row["moment"]
        ]
    );

    array_push($pastgames, $newgame);
}

// Add players to games (liste d'engagement)
foreach (VolscoreDB::getGames() as $game) {
    foreach (VolscoreDB::getMembers($game->receivingTeamId) as $member) {
        VolscoreDB::makePlayer($member->id, $game->number);
    }
    foreach (VolscoreDB::getMembers($game->visitingTeamId) as $member) {
        VolscoreDB::makePlayer($member->id, $game->number);
    }
}

// Early assessment
$teamHasPlayedIsOK = !VolscoreDB::teamHasPlayed(VolscoreDB::getTeam(1));

// Add scores to each past game
foreach ($pastgames as $game) {
    $game = VolscoreDB::getGame($game->number); // because some fields are not initialized
    while (!VolscoreDB::gameIsOver($game)) {
        $newset = VolscoreDB::addSet($game);
        $aTeamThatHasPlayed = $game->receivingTeamId;

        // create positions for this set
        $dbh = VolscoreDB::connexionDB();
        $query = "SELECT players.id FROM players INNER JOIN members ON players.member_id = members.id WHERE game_id = ".$game->number." AND team_id = ".$game->receivingTeamId." ORDER BY RAND();";
        $statement = $dbh->prepare($query); // Prepare query
        $statement->execute(); // Executer la query
        $poss = [];
        while ($row = $statement->fetch()) $poss[] = $row['id'];
        VolscoreDB::setPositions($newset->id,$game->receivingTeamId,$poss[0],$poss[1],$poss[2],$poss[3],$poss[4],$poss[5]);
        $query = "SELECT players.id FROM players INNER JOIN members ON players.member_id = members.id WHERE game_id = ".$game->number." AND team_id = ".$game->visitingTeamId." ORDER BY RAND();";
        $statement = $dbh->prepare($query); // Prepare query
        $statement->execute(); // Executer la query
        $poss = [];
        while ($row = $statement->fetch()) $poss[] = $row['id'];
        VolscoreDB::setPositions($newset->id,$game->visitingTeamId,$poss[0],$poss[1],$poss[2],$poss[3],$poss[4],$poss[5]);
        while (!VolscoreDB::setIsOver($newset)) {
            if (random_int(0, 1) == 0) {
                VolscoreDB::addPoint($newset,true);
            } else {
                VolscoreDB::addPoint($newset,false);
            }    
        }    
    }    
}    

// Start those tests now 
echo "<h1>Tests</h1>";

echo "Test getGames() -> ";
$games = VolscoreDB::getGames();
if (count($games) == 6 && $games[0]->receivingTeamName == "Froideville" && $games[1]->visitingTeamName == "Lutry" ) {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}
echo "<hr>";


echo "Test getTeam(number) -> ";
$team = VolscoreDB::getTeam(3);
if ($team->name === "Froideville") {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}
echo "<hr>";


echo "Test getCaptain(teamid) -> ";
$cap = VolscoreDB::getCaptain(VolscoreDB::getTeam(3));
if ($cap->last_name === "Stewart") {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}
echo "<hr>";


echo "Test getMember(memberid) -> ";
if (VolscoreDB::getMember(33)->last_name === "Holmes") {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}

echo "<hr>";


echo "Test makePlayer(memberid,gameid) -> ";
if (!VolscoreDB::makePlayer(999,1) && 
    !VolscoreDB::makePlayer(1,999) &&
    !VolscoreDB::makePlayer(1,1) &&
    VolscoreDB::makePlayer(5,1)) {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}

echo "<hr>";


echo "Test getLibero(teamid) -> ";
$lib = VolscoreDB::getLibero(VolscoreDB::getTeam(2));
if ($lib->last_name === "Eaton") {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}

echo "<hr>";
echo "Test getMembers(teamid) -> ";

if (count(VolscoreDB::getMembers(2)) == 12 && VolscoreDB::getMembers(5)[0]->first_name == "Theodore") {
    echo "<span style='background-color:green; padding:3px'>OK</span>";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>";
}
echo "<hr>";

echo "Test getGamesByTime -> ";
if (count(VolscoreDB::getGamesByTime(TimeInThe::Past)) == 3) {
    echo "Past <span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "Past <span style='background-color:red; padding:3px'>ko</span>,";
}
if (count(VolscoreDB::getGamesByTime(TimeInThe::Present)) == 1) {
    echo "Present <span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "Present <span style='background-color:red; padding:3px'>ko</span>,";
}
$futureGames = count(VolscoreDB::getGamesByTime(TimeInThe::Future));
if ($futureGames < 2 || $futureGames > 3) {
    echo "Future <span style='background-color:red; padding:3px'>ko</span>,";
} else {
    echo "Future <span style='background-color:green; padding:3px'>OK</span>,";
}
echo "<hr>";

echo "Test numberOfSets -> ";
if (VolscoreDB::numberOfSets(VolscoreDB::getGame(1)) > 0 && VolscoreDB::numberOfSets(VolscoreDB::getGame(7)) == 0) {
    echo "<span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>,";
}
echo "<hr>";

echo "Test createGame -> ";
$ng1 = new Game(['type' => 'Coupe', 'level' => 'Régional-Fribourg', 'category' => 'F', 'league' => 'F4', 'location' => 'Oron', 'venue' => 'Complexe sportif', 'moment' => "$today 20:00", 'visitingTeamId' => $vis, 'receivingTeamId' => $rec]);
$ng2 = new Game(['type' => 'Coupe', 'level' => 'Régional-Fribourg', 'category' => 'F', 'league' => 'F5', 'location' => 'Oron', 'venue' => 'Complexe sportif', 'moment' => "$today 20:00", 'visitingTeamId' => 9999, 'receivingTeamId' => $rec]);
$ng3 = new Game(['type' => 'Coupe', 'level' => 'Régional-Fribourg', 'category' => 'F', 'league' => 'F6', 'location' => 'Oron', 'venue' => 'Complexe sportif', 'moment' => "$today 20:00", 'visitingTeamId' => $vis, 'receivingTeamId' => 9999]);
if (VolscoreDB::createGame($ng1) && !VolscoreDB::createGame($ng2) && !VolscoreDB::createGame($ng3)) {
    echo "<span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>,";
}
echo "<hr>";

echo "Test deleteTeam -> ";
$kill = VolscoreDB::getTeam(7);
if (VolscoreDB::deleteTeam($kill->id) && !VolscoreDB::deleteTeam(1)) {
    echo "<span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>,";
}
echo "<hr>";

echo "Test teamHasPlayed -> ";
if ($teamHasPlayedIsOK && VolscoreDB::teamHasPlayed(VolscoreDB::getTeam($aTeamThatHasPlayed))) {
    echo "<span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>,";
}
echo "<hr>";

echo "Test renameTeam -> ";
$savename = VolscoreDB::getTeam(4)->name;
$res = true;
if (VolscoreDB::renameTeam(4,"x")) $res = false;
if (VolscoreDB::renameTeam(4,VolscoreDB::getTeam(3)->name)) $res = false;
if (!VolscoreDB::renameTeam(4,"VBC Schpruntz")) $res = false;
if (VolscoreDB::getTeam(4)->name != "VBC Schpruntz") $res = false;
if (!VolscoreDB::renameTeam(4,$savename)) $res = false;

if ($res) {
    echo "<span style='background-color:green; padding:3px'>OK</span>,";
} else {
    echo "<span style='background-color:red; padding:3px'>ko</span>,";
}
echo "<hr>";

echo "Test getBookings -> ";

VolscoreDB::executeInsertQuery("INSERT INTO bookings (player_id,point_id) VALUES(3,10);");

$dbh = VolscoreDB::connexionDB();
$query = "SELECT team_id FROM players INNER JOIN members ON member_id = members.id WHERE players.id = 3";
$stmt = $dbh->prepare($query);
$stmt->execute();
$row = $stmt->fetch();
$team = VolscoreDB::getTeam($row['team_id']);
$set = VolscoreDB::getSet(1); // 10th point is in set 1

$res="<span style='background-color:green; padding:3px'>OK</span>,";
$oneBooking = VolscoreDB::getBookings($team,$set);
if ( count($oneBooking) != 1) $res="<span style='background-color:red; padding:3px'>ko</span>,";
$team->id = $team->id % 6 + 1;
$noBooking = VolscoreDB::getBookings($team,$set);
if ( count($noBooking) != 0) $res="<span style='background-color:red; padding:3px'>ko</span>,";
echo $res;
$dbh = null;


echo "<hr>";


// show the games

echo "<h1>Matchs</h1>";

$games = VolscoreDB::getGames();
foreach ($games as $game) {
    echo "Match " . $game->number . ": " . $game->receivingTeamName . " vs " . $game->visitingTeamName . ", " . $game->moment . "<br>";
    foreach (VolscoreDB::getSets($game) as $set) {
        echo "   " . $set->scoreReceiving . " - " . $set->scoreVisiting . "<br>";
    }
}
?>
</div>
<a href="/"><h1>Home</h1></a>