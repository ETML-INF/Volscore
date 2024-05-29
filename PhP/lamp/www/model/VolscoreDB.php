<?php
require 'IVolscoreDb.php';

class VolscoreDB implements IVolscoreDb {

    public static function connexionDB()
    {
        require 'credentials.php';
        $PDO = new PDO("mysql:host=$hostname; port=$portnumber; dbname=$database;", $username, $password);
        $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $PDO->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $PDO;
    }
    
    public static function executeInsertQuery($query) : ?int
    {
        try
        {
            $dbh = self::connexionDB();
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $res = $dbh->lastInsertId();
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            echo $e;
            return null;
        }
    }

    public static function executeUpdateQuery($query)
    {
        try
        {
            $dbh = self::connexionDB();
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $dbh = null;
        } catch (PDOException $e) {
        }
    }

    public static function getTeams() : array
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM teams";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $res = [];
            while ($row = $statement->fetch()) {
                $res[] = new Team($row);
            }
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }
    
    public static function getGames() : array
    {
        try
        {
            $dbh = self::connexionDB();
            $query = 
                "SELECT games.id as number, type, level,category,league,receiving_id as receivingTeamId,r.name as receivingTeamName,visiting_id as visitingTeamId,v.name as visitingTeamName,location as place,venue,moment ".
                "FROM games INNER JOIN teams r ON games.receiving_id = r.id INNER JOIN teams v ON games.visiting_id = v.id";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $statement->execute();
            $res = [];
            while ($rec = $statement->fetch()) {
                $game = new Game($rec);
                $game->scoreReceiving = 0;
                $game->scoreVisiting = 0;
                foreach (self::getSets($game) as $set) {
                    if (VolscoreDB::setIsOver($set)) {
                        if ($set->scoreReceiving > $set->scoreVisiting) {
                            $game->scoreReceiving++;
                        } else {
                            $game->scoreVisiting++;
                        }
                    }
                }
                $res[] = $game;
            }
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getGamesByTime($period) : array
    {
        $query = "SELECT games.id as number, type, level,category,league,receiving_id as receivingTeamId,r.name as receivingTeamName,visiting_id as visitingTeamId,v.name as visitingTeamName,location as place,venue,moment " .
                 "FROM games INNER JOIN teams r ON games.receiving_id = r.id INNER JOIN teams v ON games.visiting_id = v.id ";
    
        switch ($period) {
            case TimeInThe::Past:
                $query .= "WHERE moment < now()";
                break;
            case TimeInThe::Present:
                $query .= "WHERE DATE(moment) = DATE(now())";
                break;
            case TimeInThe::Future:
                $query .= "WHERE moment > now()";
                break;
        }
        $query .= " ORDER BY moment, games.id";
        try {
            $pdo = self::connexionDB();
            $statement = $pdo->prepare($query);
            $statement->setFetchMode(PDO::FETCH_CLASS, 'Game');
            $statement->execute();
            $res = $statement->fetchAll();
            foreach ($res as $i => $game) {
                $res[$i]->scoreReceiving = 0;
                $res[$i]->scoreVisiting = 0;
                foreach (self::getSets($game) as $set) {
                    if ($set->scoreReceiving > $set->scoreVisiting) {
                        $res[$i]->scoreReceiving++;
                    } else {
                        $res[$i]->scoreVisiting++;
                    }
                }
            }
            return $res;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
        return array(); // empty in case of error
    }
    
    public static function getTeam($number) : Team
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM teams WHERE id = $number";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $queryResult = $statement->fetch(); // Affiche les résultats
            $dbh = null;
            return new Team($queryResult);
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function teamHasPlayed($team) : bool
    {
        foreach (self::getGamesByTime(TimeInThe::Past) as $game) {
            // a game is played if it's in the past and one team won 3 sets
            if (($game->receivingTeamId == $team->id || $game->visitingTeamId == $team->id) && ($game->scoreReceiving == 3 || $game->scoreVisiting == 3)) return true;
        }
        return false;
    }

    public static function deleteTeam ($teamid) : bool
    {
        try
        {
            $dbh = self::connexionDB();
            $statement = $dbh->prepare("DELETE FROM teams WHERE id=$teamid"); // Prepare query
            $statement->execute(); // Executer la query
            $dbh = null;
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function renameTeam($number, $newname) : bool
    {
        if (strlen($newname) > 2) { // minimal length = 3
            try
            {
                $dbh = self::connexionDB();
                $statement = $dbh->prepare("UPDATE teams SET name='$newname' WHERE id=$number"); // Prepare query
                $statement->execute(); // Executer la query
                $dbh = null;
                return true;
            } catch (PDOException $e) {
                return false;
            }
        }
        return false;
    }

    public static function getGame($number) : ?Game
    {
        try
        {
            $dbh = self::connexionDB();
            $query =
                "SELECT games.id as number, type, level,category,league,receiving_id as receivingTeamId,r.name as receivingTeamName,visiting_id as visitingTeamId,v.name as visitingTeamName,location as place,venue,moment,toss " .
                "FROM games INNER JOIN teams r ON games.receiving_id = r.id INNER JOIN teams v ON games.visiting_id = v.id " .
                "WHERE games.id=$number";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            if (!($queryResult = $statement->fetch())) throw new Exception("Game not found"); 
            $dbh = null;
            $res = new Game($queryResult);
            // Update score
            $res->scoreReceiving = 0;
            $res->scoreVisiting = 0;
            $sets = self::getSets($res);
            foreach ($sets as $set)
            {
                if (VolscoreDB::setIsOver($set)) {
                    if ($set->scoreReceiving > $set->scoreVisiting)
                    {
                        $res->scoreReceiving++;
                    }
                    else
                    {
                        $res->scoreVisiting++;
                    }
                }
            }
            return $res;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function saveGame($game)
    {
        $query = "UPDATE games SET ". 
            "type = '{$game->type}',".
            "level = '{$game->level}',".
            "category = '{$game->category}',".
            "league = '{$game->league}',".
            "location = '{$game->location}',".
            "venue = '{$game->venue}',".
            "moment = '{$game->moment}',".
            "toss = '{$game->toss}' ". 
            "WHERE id={$game->number}";
        return self::executeUpdateQuery($query);
    }

    // TODO une erreur a été détectée ici
    public static function getMembers($teamid) : array
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM members WHERE team_id = $teamid";
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $res = [];
            while ($row = $statement->fetch()) {
                $res[] = new Member($row);
            }
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getMember($memberid) : ?Member
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM members WHERE id = $memberid";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            if (!$queryResult = $statement->fetch()) return null;
            $dbh = null;
            return new Member($queryResult);
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getCaptain($team) : Member
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM members WHERE team_id = {$team->id} AND role='C'";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $queryResult = $statement->fetch(); // Affiche les résultats
            $dbh = null;
            
            return new Member($queryResult);
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getLibero($team) : Member
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM members WHERE team_id = {$team->id} AND libero=1";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $queryResult = $statement->fetch(); // Affiche les résultats
            $dbh = null;
            return new Member($queryResult);
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    // Ajout d'une méthode pour vérifier si un ID d'équipe existe
    private static function teamExists($teamId) {
        $dbh = self::connexionDB();
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        $exists = $stmt->fetchColumn() > 0;
        $dbh = null;
        return $exists;
    }

    // Modification de la méthode createGame
    public static function createGame($game) : ?int
    {
        if (!self::teamExists($game->receivingTeamId) || !self::teamExists($game->visitingTeamId)) {
            return null;
        }

        $query = "INSERT INTO games (type, level, category, league, location, venue, moment, receiving_id, visiting_id) ".
                 "VALUES('{$game->type}', '{$game->level}', '{$game->category}', '{$game->league}', '{$game->location}', '{$game->venue}', '{$game->moment}', {$game->receivingTeamId}, {$game->visitingTeamId});";
                 
        return self::executeInsertQuery($query);
    }

    public static function validatePlayer($gameid,$memberid)
    {
        $query = "UPDATE players SET validated = 1 WHERE game_id=$gameid AND member_id=$memberid";
        return self::executeUpdateQuery($query);
    }

    public static function getSets($game) : array
    {
        $dbh = self::connexionDB();
        $res = array();
        
        $query = "SELECT sets.id, number, start, end, game_id, ".
        "(SELECT COUNT(points.id) FROM points WHERE team_id = receiving_id and set_id = sets.id) as recscore, ".
        "(SELECT COUNT(points.id) FROM points WHERE team_id = visiting_id and set_id = sets.id) as visscore ".
        "FROM games INNER JOIN sets ON games.id = sets.game_id ".
        "WHERE game_id = $game->number ".
        "ORDER BY sets.number";
        
        $statement = $dbh->prepare($query); // Prepare query
        $statement->execute(); // Executer la query
        while ($row = $statement->fetch()) {
            $newset = array(
                "game" => $row['game_id'],
                "number" => $row['number']
            );
            $newset['id'] = $row['id'];
            if (!is_null($row['start'])) $newset['start'] = $row['start'];
            if (!is_null($row['end'])) $newset['end'] = $row['end'];
            if (!is_null($row['recscore'])) $newset['scoreReceiving'] = intval($row['recscore']);
            if (!is_null($row['visscore'])) $newset['scoreVisiting'] = intval($row['visscore']);
        
            array_push($res, new Set($newset));
        }
        $dbh = null;
        return $res;
      }
      
    public static function gameIsOver($game) : bool
    {
        $sets = VolscoreDB::getSets($game);
        $recwin = 0;
        $viswin = 0;
        foreach ($sets as $set) {
            if ($set->scoreReceiving > $set->scoreVisiting) $recwin++;
            if ($set->scoreReceiving < $set->scoreVisiting) $viswin++;
        }
        return ($recwin == 3 || $viswin == 3);
    }
      
    public static function getSet($setid) : Set
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT *,". 
                "(SELECT COUNT(points.id) FROM points WHERE team_id = receiving_id and set_id = sets.id) as scoreReceiving, ".
                "(SELECT COUNT(points.id) FROM points WHERE team_id = visiting_id and set_id = sets.id) as scoreVisiting ".
                "FROM games INNER JOIN sets ON games.id = sets.game_id ". 
                "WHERE sets.id=$setid";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $queryResult = $statement->fetch(); // Affiche les résultats
            $dbh = null;
            return new Set($queryResult);
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getSetScoringSequence($set) : array
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM points WHERE set_id={$set->id}";
            $statement = $dbh->prepare($query); // Prepare query
            $statement->execute(); // Executer la query
            $receivingScores = [];
            $visitingScores = [];
            $servingTeamId = 0;
            $points = 0;
            while ($point = $statement->fetch()) {
                $points++;
                if ($point['team_id'] != $servingTeamId) {
                    $lastTotal = isset($pointsOnServe[$servingTeamId]) ? $pointsOnServe[$servingTeamId][count($pointsOnServe[$servingTeamId])-1] : 0;
                    $pointsOnServe[$servingTeamId][] = $lastTotal+$points;
                    $points = 0;
                    $servingTeamId = $point['team_id'];
                }
            }
            // score the last points
            $points++;
            $lastTotal = isset($pointsOnServe[$servingTeamId]) ? $pointsOnServe[$servingTeamId][count($pointsOnServe[$servingTeamId])-1] : 0;
            $pointsOnServe[$servingTeamId][] = $lastTotal+$points;
            $dbh = null;
            return $pointsOnServe;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function setIsOver($set) : bool
    {
        $score1 = 0;
        $score2 = 0;
        $limit = $set->number == 5 ? 15 : 25;
        $pdo = self::connexionDB();
        $stmt = $pdo->prepare("SELECT COUNT(id) as points, team_id 
                            FROM points 
                            WHERE set_id = :set_id 
                            GROUP BY team_id");
        $stmt->bindValue(':set_id', $set->id);
        $stmt->execute();

        $result = $stmt->fetchAll();
        if(count($result) >= 1) $score1 = $result[0]['points'];
        if(count($result) >= 2) $score2 = $result[1]['points'];

        // Assess
        if($score1 < $limit && $score2 < $limit) return false; // no one has enough points
        if(abs($score2-$score1) < 2) return false; // one team has enough points but a 1-point lead only
        return true; // if we get there, we have a winner

    }

    public static function addSet($game) : Set
    {
        $sets = VolscoreDB::getSets($game);
        if (count($sets) >= 5) throw new Exception('Ajout de set à un match qui en a déjà 5');
        $newset = new Set();
        $newset->game_id = $game->number;
        $newset->number = count($sets)+1;
        $query = "INSERT INTO sets (number,game_id) VALUES(". $newset->number .",". $newset->game_id .");";
        $newset->id = self::executeInsertQuery($query);
        return $newset;
    }
      
    private static function getLastPoint ($set) : ?Point
    {
        $pdo = self::connexionDB();
        $stmt = $pdo->prepare("SELECT * FROM points WHERE set_id = :set_id ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(':set_id', $set->id);
        $stmt->execute();
        if (!$record=$stmt->fetch()) return null;
        return (new Point($record));
    }

    public static function getPoints($set) : array
    {
        $pdo = self::connexionDB();
        $stmt = $pdo->prepare("SELECT * FROM points WHERE set_id = :set_id ORDER BY id ASC");
        $stmt->bindValue(':set_id', $set->id);
        $stmt->execute();
        $points = [];

        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $points[] = new Point($record);
        }

        return $points;
    }


    private static function getPointBeforeLast ($set)
    {
        $pdo = self::connexionDB();
        $stmt = $pdo->prepare("SELECT * FROM points WHERE set_id = :set_id ORDER BY id DESC LIMIT 2");
        $stmt->bindValue(':set_id', $set->id);
        $stmt->execute();
        $stmt->fetch(); // "burn" one
        if (!$record=$stmt->fetch()) return null;
        return (new Point($record));
    }

    private static function setExists($setId) {
        $dbh = self::connexionDB();
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM sets WHERE id = ?");
        $stmt->execute([$setId]);
        $exists = $stmt->fetchColumn() > 0;
        $dbh = null;
        return $exists;
    }

    public static function getPosition($setid, $teamid): ?Position {
        try {
            if (!self::teamExists($teamid)) {
                echo "Erreur : L'équipe spécifiée n'existe pas.";
                return null;
            }
        
            if (!self::setExists($setid)) {
                echo "Erreur : Le set spécifié n'existe pas.";
                return null;
            }


            $dbh = self::connexionDB();
            $query = "SELECT * FROM positions WHERE set_id = :setid AND team_id = :teamid LIMIT 1;";
            $statement = $dbh->prepare($query);
    
            // Utilisez le binding de paramètres pour éviter les injections SQL
            $statement->bindParam(':setid', $setid, PDO::PARAM_INT);
            $statement->bindParam(':teamid', $teamid, PDO::PARAM_INT);
    
            $statement->execute();
            $positionData = $statement->fetch(PDO::FETCH_ASSOC);
    
            if ($positionData) {
                // Créez une instance de Position et initialisez les propriétés
                $position = new Position();
                $position->id = $positionData['id'];
                $position->set_id = $positionData['set_id'];
                $position->team_id = $positionData['team_id'];
                $position->player_position_1_id = $positionData['starter_1_id'];
                $position->player_position_2_id = $positionData['starter_2_id'];
                $position->player_position_3_id = $positionData['starter_3_id'];
                $position->player_position_4_id = $positionData['starter_4_id'];
                $position->player_position_5_id = $positionData['starter_5_id'];
                $position->player_position_6_id = $positionData['starter_6_id'];
                $position->player_sub_1_id = $positionData['sub_1_id'];
                $position->player_sub_2_id = $positionData['sub_2_id'];
                $position->player_sub_3_id = $positionData['sub_3_id'];
                $position->player_sub_4_id = $positionData['sub_4_id'];
                $position->player_sub_5_id = $positionData['sub_5_id'];
                $position->player_sub_6_id = $positionData['sub_6_id'];
                $position->sub_in_point_1_id = $positionData['sub_in_point_1_id'];
                $position->sub_out_point_1_id = $positionData['sub_out_point_1_id'];
                $position->sub_in_point_2_id = $positionData['sub_in_point_2_id'];
                $position->sub_out_point_2_id = $positionData['sub_out_point_2_id'];
                $position->sub_in_point_3_id = $positionData['sub_in_point_3_id'];
                $position->sub_out_point_3_id = $positionData['sub_out_point_3_id'];
                $position->sub_in_point_4_id = $positionData['sub_in_point_4_id'];
                $position->sub_out_point_4_id = $positionData['sub_out_point_4_id'];
                $position->sub_in_point_5_id = $positionData['sub_in_point_5_id'];
                $position->sub_out_point_5_id = $positionData['sub_out_point_5_id'];
                $position->sub_in_point_6_id = $positionData['sub_in_point_6_id'];
                $position->sub_out_point_6_id = $positionData['sub_out_point_6_id'];
                $position->final = $positionData['final'];
    
                return $position;
            } else {
                $dbh = null;
                return null; // Aucune position trouvée
            }
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            $dbh = null;
            return null;
        }
    }    

    private static function getLastPointOfTeam ($set, $teamid) : ?Point
    {
        $pdo = self::connexionDB();
        $stmt = $pdo->prepare("SELECT * FROM points WHERE set_id = :set_id AND team_id = $teamid ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(':set_id', $set->id);
        $stmt->execute();
        if (!$record=$stmt->fetch()) return null;
        return (new Point($record));
    }

    public static function addPoint($set, $receiving)
    {
        $game = self::getGame($set->game_id);
        $scoringTeamId = ($receiving ? $game->receivingTeamId : $game->visitingTeamId);

        // get last point of the set
        $lastPoint = self::getLastPoint($set);
        // get last point of the set score by that team
        $lastPointOfTeam = self::getLastPointOfTeam($set, $scoringTeamId);

        // use info for rotation        
        if (!$lastPoint) {
            $position = 1; // at the beginning of the game, serve is on position 1 on both sides
            // save set start time
            $query = "UPDATE `sets` SET start = CURRENT_TIMESTAMP WHERE `sets`.id = ".$set->id;
            self::executeUpdateQuery($query);
        } elseif (!$lastPointOfTeam) {
            $position = 2; // first point of the serve-receiving team
        } elseif ($lastPoint->team_id != $scoringTeamId){
            $position = $lastPointOfTeam->position_of_server % 6 + 1; // change of serve -> rotation
        } else {
            $position = $lastPoint->position_of_server; // no change
        }
        
        $query =
             "INSERT INTO points (team_id, set_id, position_of_server) " .
             "VALUES(". ($receiving ? $game->receivingTeamId : $game->visitingTeamId) . ",".$set->id.",$position);";
        self::executeInsertQuery($query);
    }

    public static function addTimeOut($teamid, $setid)
    {
        $lastPoint = self::getLastPoint(self::getSet($setid));
        $query = "INSERT INTO timeouts (team_id, set_id, point_id) VALUES($teamid,$setid,".$lastPoint->id.");";
        self::executeInsertQuery($query);
    }

    public static function getTimeouts($teamid,$setid) : array
    {
        $query = "SELECT pts.`timestamp` ,".
                    "(SELECT COUNT(pts2.id) FROM points pts2 WHERE team_id = g.receiving_id and set_id = s.id and pts2.id <= pts.id) as scoreReceiving, ".
                    "(SELECT COUNT(pts3.id) FROM points pts3 WHERE team_id = g.visiting_id and set_id = s.id and pts3.id <= pts.id) as scoreVisiting ".
                "FROM timeouts ".
                    "INNER JOIN teams t ON team_id = t.id ".
                    "INNER JOIN points pts ON point_id = pts.id ".
                    "INNER JOIN `sets` s ON timeouts.set_id = s.id ".
                    "INNER JOIN games g ON game_id = g.id ".
                "WHERE s.id= :setid and t.id= :teamid ;";
        
        $dbh = self::connexionDB();
        $stmt = $dbh->prepare($query);
        $stmt->bindValue(':setid',$setid);
        $stmt->bindValue(':teamid',$teamid);
        $stmt->execute();
        $dbh = null;
        return $stmt->fetchall();
    }

    public static function registerSetEndTimestamp($setid)
    {
        $query = "UPDATE `sets` SET end = CURRENT_TIMESTAMP WHERE `sets`.id = ".$setid;
        self::executeUpdateQuery($query);
    }

    public static function nextServer($set) : Member
    {
        $game = self::getGame($set->game_id);
        // get last point of the set
        $lastPoint = self::getLastPoint($set);

        // use info
        if (!$lastPoint) {
            $servingTeamId = $game->receivingTeamId; // by default for now (ignore toss)
            $position = 1;
        } else {            
            $servingTeamId = $lastPoint->team_id; 
            $position = $lastPoint->position_of_server; 
        }

        // find the player
        $positions = self::getCourtPlayers($set->game_id, $set->id,$servingTeamId);
        return $positions[$position-1];
    }

    public static function nextTeamServing($set) : int
    {
        $game = self::getGame($set->game_id);
        // get last point of the set
        $lastPoint = self::getLastPoint($set);

        // use info
        if (!$lastPoint) {
            $servingTeamId = $game->receivingTeamId; // by default for now (ignore toss)
            $position = 1;
        } else {            
            $servingTeamId = $lastPoint->team_id; 
            $position = $lastPoint->position_of_server; 
        }

        return $servingTeamId;
    }

    public static function numberOfSets($game) : int
    {
        return count(self::getSets($game));
    }

    public static function makePlayer($memberid,$gameid) : bool
    {
        $game = self::getGame($gameid);
        if ($game == null) return false;
        $member = self::getMember($memberid);
        if ($member == null) return false;
        if ($member->team_id != $game->receivingTeamId && $member->team_id != $game->visitingTeamId) return false;

        $query =
             "INSERT INTO players (game_id, member_id, number) " .
             "VALUES($gameid,$memberid,".$member->number.");";
        self::executeInsertQuery($query);
        return true;
    }

    public static function getPlayer($memberid,$gameid) : ?Member
    {
        $pdo = self::connexionDB();
        $query = "SELECT members.id,members.first_name,members.last_name,members.role,members.license,members.team_id,players.id as playerid, players.number ".
        "FROM players INNER JOIN members ON member_id = members.id ". 
        "WHERE members.id = $memberid AND players.game_id = $gameid";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        if ($row = $stmt->fetch()) {
            $player = new Member($row);
            // WARNING: Trick: add some contextual player info to the Member object
            $player->playerInfo = ['playerid' => $row['playerid'], 'number' => $row['number']];
            return $player;
        } else {
            return null;
        }
    }

    private static function findPlayer($playerid) : ?Member
    {
        $pdo = self::connexionDB();
        $query = "SELECT members.id,members.first_name,members.last_name,members.role,members.license,members.team_id,players.id as playerid, players.number ".
        "FROM players INNER JOIN members ON member_id = members.id ". 
        "WHERE players.id = $playerid";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        if ($row = $stmt->fetch()) {
            $player = new Member($row);
            // WARNING: Trick: add some contextual player info to the Member object
            $player->playerInfo = ['playerid' => $row['playerid'], 'number' => $row['number']];
            return $player;
        } else {
            return null;
        }
    }

    public static function setPositions($setid, $teamid, $pos1, $pos2, $pos3, $pos4, $pos5, $pos6, $final=0)
    {
        $query =
             "INSERT INTO positions (set_id, team_id, starter_1_id, starter_2_id, starter_3_id, starter_4_id, starter_5_id, starter_6_id, final) " .
             "VALUES($setid, $teamid, $pos1, $pos2, $pos3, $pos4, $pos5, $pos6, $final);";
        self::executeInsertQuery($query);
    }

    public static function updatePositions($setid, $teamid, $pos1, $pos2, $pos3, $pos4, $pos5, $pos6, $final=0)
    {
        $query =
             "UPDATE positions SET starter_1_id=$pos1, starter_2_id=$pos2, starter_3_id=$pos3, starter_4_id=$pos4, starter_5_id=$pos5, starter_6_id=$pos6,final=$final " .
             "WHERE set_id = $setid AND team_id = $teamid;";
        
             //echo $query;

        self::executeUpdateQuery($query);
    }

    public static function setSubTeam($setid, $teamid, $sub1, $subpoint1, $sub2,$subpoint2, $sub3, $subpoint3, $sub4, $subpoint4, $sub5,$subpoint5, $sub6, $subpoint6)
    {
        $query =
             "UPDATE positions SET sub_1_id=$sub1, sub_in_point_1=$subpoint1, sub_2_id=$sub2, sub_in_point_2=$subpoint2, sub_3_id=$sub3, sub_in_point_3=$subpoint3, sub_4_id=$sub4, sub_in_point_4=$subpoint4, sub_5_id=$sub5, sub_in_point_5=$subpoint5, sub_6_id=$sub6, sub_in_point_6=$subpoint6 " .
             "WHERE set_id = $setid AND team_id = $teamid;";
        
             //echo $query;

        self::executeUpdateQuery($query);
    }

    public static function getSubTeam($setid, $teamid)
    {
        try {
            $dbh = self::connexionDB();
            
            $query = "SELECT sub_1_id, sub_in_point_1_id, sub_2_id, sub_in_point_2_id, sub_3_id, sub_in_point_3_id, " .
                    "sub_4_id, sub_in_point_4_id, sub_5_id, sub_in_point_5_id, sub_6_id, sub_in_point_6_id " .
                    "FROM positions WHERE set_id = :setid AND team_id = :teamid;";

            $statement = $dbh->prepare($query);
            
            // Bind des paramètres
            $statement->bindParam(':setid', $setid, PDO::PARAM_INT);
            $statement->bindParam(':teamid', $teamid, PDO::PARAM_INT);
            
            $statement->execute(); // Exécute la requête
            
            $result = $statement->fetch(PDO::FETCH_ASSOC); // Récupère le résultat
            $dbh = null;
            return $result; // Retourne le résultat
        } catch (PDOException $e) {
            // Gestion des erreurs
            echo $e;
            return null; // Ou gérer l'erreur comme souhaité
        }
    }


    public static function setSub($setid, $teamid, $sub, $subPoint, $subPosition)
    {
        // Déterminer les noms des colonnes à mettre à jour en fonction de $subPosition
        $subIdColumn = "sub_" . $subPosition . "_id";
        $subPointColumn = "sub_in_point_" . $subPosition . "_id";
        
        $query =
            "UPDATE positions SET $subIdColumn = $sub, $subPointColumn = $subPoint " .
            "WHERE set_id = $setid AND team_id = $teamid;";
        
        //echo $query;

        self::executeInsertQuery($query);
    }

    public static function setSubOutPoint($setid, $teamid, $subPoint, $subPosition)
    {
        // Déterminer les noms des colonnes à mettre à jour en fonction de $subPosition
        $subPointColumn = "sub_out_point_" . $subPosition . "_id";
        
        $query =
            "UPDATE positions SET $subPointColumn = $subPoint " .
            "WHERE set_id = $setid AND team_id = $teamid;";
        
        //echo $query;

        self::executeInsertQuery($query);
    }

    

    public static function getStartingPositions($setid, $teamid, &$isFinal = NULL) : array
    {
        try
        {
            $res = [];
            $dbh = self::connexionDB();
            if ($setid > 0) { 
                $query = "SELECT * FROM positions WHERE set_id=$setid AND team_id=$teamid;";
            } else { // get last used positions
                $query = "SELECT * FROM positions WHERE team_id=$teamid ORDER BY id DESC LIMIT 1;";
            }
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $positions = $statement->fetch();
            if (!$positions) return $res;
            if ($setid == 0) { // get it from the position sheet
                $setid = $positions['set_id'];
            }
            // build the list
            for ($pos = 1; $pos <= 6; $pos++) {
                $res[] = self::findPlayer($positions['starter_'.$pos.'_id']);
            }
            $isFinal = $positions['final'];
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getSubPositions($setid, $teamid, &$isFinal = NULL) : array
    {
        try
        {
            $res = [];
            $dbh = self::connexionDB();
            if ($setid > 0) { 
                $query = "SELECT * FROM positions WHERE set_id=$setid AND team_id=$teamid;";
            } else { // get last used positions
                $query = "SELECT * FROM positions WHERE team_id=$teamid ORDER BY id DESC LIMIT 1;";
            }
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $positions = $statement->fetch();
            if (!$positions) return $res;
            if ($setid == 0) { // get it from the position sheet
                $setid = $positions['set_id'];
            }
            // build the list
            for ($pos = 1; $pos <= 6; $pos++) {
                if($positions['sub_'.$pos.'_id'] != null){
                    $res[] = self::findPlayer($positions['sub_'.$pos.'_id']);
                }
                else{
                    $res[] = "";
                }
                
            }
            $isFinal = $positions['final'];
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return array_fill(0, 6, null);
        }
    }

    public static function getSubInpoints($setid, $teamid, &$isFinal = NULL) : array
    {
        try
        {
            $res = [];
            $dbh = self::connexionDB();
            if ($setid > 0) { 
                $query = "SELECT * FROM positions WHERE set_id=$setid AND team_id=$teamid;";
            } else { // get last used positions
                $query = "SELECT * FROM positions WHERE team_id=$teamid ORDER BY id DESC LIMIT 1;";
            }
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $positions = $statement->fetch();
            if (!$positions) return $res;
            if ($setid == 0) { // get it from the position sheet
                $setid = $positions['set_id'];
            }
            // build the list
            for ($pos = 1; $pos <= 6; $pos++) {
                $res[] = $positions['sub_in_point_'.$pos.'_id'];
            }
            $isFinal = $positions['final'];
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getSubOutPoints($setid, $teamid, &$isFinal = NULL) : array
    {
        try
        {
            $res = [];
            $dbh = self::connexionDB();
            if ($setid > 0) { 
                $query = "SELECT * FROM positions WHERE set_id=$setid AND team_id=$teamid;";
            } else { // get last used positions
                $query = "SELECT * FROM positions WHERE team_id=$teamid ORDER BY id DESC LIMIT 1;";
            }
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $positions = $statement->fetch();
            if (!$positions) return $res;
            if ($setid == 0) { // get it from the position sheet
                $setid = $positions['set_id'];
            }
            // build the list
            for ($pos = 1; $pos <= 6; $pos++) {
                $res[] = $positions['sub_out_point_'.$pos.'_id'];
            }
            $isFinal = $positions['final'];
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getBenchPlayers($gameid,$setid,$teamid)
    {
        try
        {
            $res = VolscoreDB::getRoster($gameid,$teamid); // start with full team
            $res = array_udiff($res, self::getCourtPlayers($gameid,$setid,$teamid), function ($member1, $member2) {
                return $member1->id - $member2->id;
            });            
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getCourtPlayers($gameid,$setid,$teamid)
    {
        try
        {
            $res = [];
            $dbh = self::connexionDB();
            $query = "SELECT * FROM positions WHERE set_id = $setid AND team_id = $teamid";
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $positions = $statement->fetch();
            for ($pos = 1; $pos <= 6; $pos++) { // find the player at each position
                $playerid = $positions['starter_'.$pos.'_id']; // assume it's the starter
                if ($positions['sub_in_point_'.$pos.'_id'] && !$positions['sub_out_point_'.$pos.'_id']) { // starter has been subbed
                    $playerid = $positions['sub_'.$pos.'_id'];
                }
                $res[] = self::findPlayer($playerid);
            }
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getRoster($gameid, $teamid) : array
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT members.id,members.first_name,members.last_name,members.role,members.license,players.id as playerid, players.number,players.validated ".
                    "FROM players INNER JOIN members ON member_id = members.id ". 
                    "WHERE game_id = $gameid AND members.team_id = $teamid";
            $statement = $dbh->prepare($query); // Prepare query    
            $statement->execute(); // Executer la query
            $res = [];
            while ($row = $statement->fetch()) {
                $member = new Member($row);
                // WARNING: Trick: add some contextual player info to the Member object
                $member->playerInfo = ['playerid' => $row['playerid'], 'number' => $row['number'], 'validated' => $row['validated']];
                $res[] = $member;
            }
            $dbh = null;
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function giveBooking ($playerid, $setid, $severity)
    {
        $lastPoint = self::getLastPoint(self::getSet($setid));
        $pdo = self::connexionDB();
        $query = "INSERT INTO bookings (player_id, point_id, severity) VALUES($playerid, ".$lastPoint->id.", $severity);";
        self::executeInsertQuery($query);
    }

    public static function getBookings($team,$set) : array
    {
        $query = "SELECT pts.`timestamp` , p.`number`, m.last_name, severity ,  ".
                    "(SELECT COUNT(pts2.id) FROM points pts2 WHERE team_id = g.receiving_id and set_id = s.id and pts2.id <= pts.id) as scoreReceiving, ".
                    "(SELECT COUNT(pts3.id) FROM points pts3 WHERE team_id = g.visiting_id and set_id = s.id and pts3.id <= pts.id) as scoreVisiting ".
                "FROM bookings ".
                    "INNER JOIN players p ON player_id = p.id ".
                    "INNER JOIN members m ON member_id = m.id ".
                    "INNER JOIN points pts ON point_id = pts.id ".
                    "INNER JOIN `sets` s ON set_id = s.id ".
                    "INNER JOIN games g ON s.game_id = g.id ".
                "WHERE s.id= :setid and m.team_id= :teamid ;";
        
        $dbh = self::connexionDB();
        $stmt = $dbh->prepare($query);
        $stmt->bindValue(':setid',$set->id);
        $stmt->bindValue(':teamid',$team->id);
        $stmt->execute();
        $dbh = null;
        return $stmt->fetchall();
    }

    public static function getUserByUsername($username)
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM users WHERE username = :username";
            $statement = $dbh->prepare($query);
            $statement->bindParam(':username', $username, PDO::PARAM_STR);
            $statement->execute();
            $queryResult = $statement->fetch();
            $dbh = null;
            return $queryResult;
        }
        catch (PDOException $e)
        {
            print 'Error!: ' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getUserByMail($email)
    {
        try{
            $dbh = self::connexionDB();

            $query = $dbh->prepare("SELECT id FROM users WHERE email = :email");

            $query->bindParam(':email', $email, PDO::PARAM_STR);

            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $dbh = null;
            
            return $row['id'];
        }
        catch (PDOException $e)
        {
            echo 'Error!: ' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getUser($id)
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM users WHERE id = :id";
            $statement = $dbh->prepare($query);
            $statement->bindParam(':id', $id, PDO::PARAM_STR);
            $statement->execute();
            $queryResult = $statement->fetch();
            $dbh = null;
            return $queryResult;
        }
        catch (PDOException $e)
        {
            print 'Error!: ' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function insertToken($id, $token)
    {
        $pdo = self::connexionDB();

        $query = $pdo->prepare("UPDATE users SET token = :token WHERE id = :id");

        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->bindParam(':token', $token, PDO::PARAM_STR);

        $query->execute();

        if ($query->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function getUserByToken($token)
    {
        try
        {
            $dbh = self::connexionDB();

            $query = "SELECT * FROM users WHERE token = :token";
            $statement = $dbh->prepare($query);

            $statement->bindParam(':token', $token, PDO::PARAM_STR);

            $statement->execute();

            $user = $statement->fetch(PDO::FETCH_ASSOC);

            $dbh = null;

            return $user;
        }
        catch (PDOException $e)
        {
            print 'Error!: ' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function updateUserPassword($userId, $newPassword)
    {
        try
        {
            $dbh = self::connexionDB();

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $query = "UPDATE users SET password = :password, token = NULL WHERE id = :id";

            $statement = $dbh->prepare($query);

            $statement->bindParam(':id', $userId, PDO::PARAM_INT);
            $statement->bindParam(':password', $hashedPassword, PDO::PARAM_STR);

            $statement->execute();

            if ($statement->rowCount() > 0) {
                $dbh = null;
                return true;
            } else {
                $dbh = null;
                return false;
            }
        }
        catch (PDOException $e)
        {
            print 'Error!: ' . $e->getMessage() . '<br/>';
            return false;
        }
    }

    public static function getUserRoleById($userId)
    {
        try {
            $dbh = self::connexionDB();

            $stmt = $dbh->prepare("SELECT users.username, roles.name AS role_name FROM users JOIN roles ON users.role_id = roles.id WHERE users.id = ?");
            
            $stmt->execute([$userId]);
        
            $user = $stmt->fetch();

            return $user['role_name'];

        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getAllUsers()
    {
        try
        {
            $dbh = self::connexionDB();
            $query = "SELECT * FROM users";

            $statement = $dbh->prepare($query);
            
            $statement->execute();

            $queryResult = $statement->fetchAll(PDO::FETCH_ASSOC);

            $dbh = null;
            
            return $queryResult;
        }
        catch (PDOException $e)
        {
            print 'Error!: ' . $e->getMessage() . '<br/>';
            return null;
        }
    }

    public static function getSignaturesByUserId($id){

        $db = self::connexionDB();
    
        $query = "SELECT * FROM signatures WHERE user_id = :user_id";

        $statement = $db->prepare($query);

        $statement->bindParam(':user_id', $id, PDO::PARAM_INT);

        $statement->execute();

        $signatures = $statement->fetchAll(PDO::FETCH_ASSOC);
    
        return $signatures;
    }

    public static function getGamesByUserId($id){

        $db = self::connexionDB();

        $query = "SELECT g.* FROM signatures s
                INNER JOIN games g ON s.game_id = g.id
                WHERE s.user_id = :user_id";

        $statement = $db->prepare($query);

        $statement->bindParam(':user_id', $id, PDO::PARAM_INT);

        $statement->execute();

        $games = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $games;

    }

    public static function getRoles(){

        $db = self::connexionDB();

        $query = "SELECT * FROM roles";
    
        $statement = $db->prepare($query);
    
        $statement->execute();
    
        $roles = $statement->fetchAll(PDO::FETCH_ASSOC);
    
        return $roles;
    }

    public static function insertUser($username, $password, $phone, $email, $role_id, $validate = false) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
            $db = self::connexionDB();
    
            $query = "INSERT INTO users (username, password, phone, email, validate, role_id) 
                      VALUES (:username, :password, :phone, :email, :validate, :role_id)";
    
            $statement = $db->prepare($query);
    
            $validate_int = $validate ? 1 : 0;
    
            $statement->bindParam(':username', $username, PDO::PARAM_STR);
            $statement->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $statement->bindParam(':phone', $phone, PDO::PARAM_STR);
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
            $statement->bindParam(':validate', $validate_int, PDO::PARAM_INT);
            $statement->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    
            if ($statement->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function updateValidateUserState($id, $state) {
        try {
            $db = self::connexionDB();
    
            $query = "UPDATE users SET validate = :state WHERE id = :id";
    
            $statement = $db->prepare($query);
            $statement->bindParam(':id', $id, PDO::PARAM_INT);
            $statement->bindParam(':state', $state, PDO::PARAM_INT);
    
            if ($statement->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function insertSignature($user_id, $game_id, $role_id) {

        try {
            $db = self::connexionDB();
    
            $checkQuery = "SELECT COUNT(*) FROM signatures WHERE game_id = :game_id AND user_id = :user_id AND role_id = :role_id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
            $checkStmt->execute();
    
            if ($checkStmt->fetchColumn() > 0) {
                return false;
            }
    
            $query = "INSERT INTO signatures (game_id, user_id, role_id, token_signature) VALUES (:game_id, :user_id, :role_id, NULL)";
            $statement = $db->prepare($query);
            $statement->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $statement->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    
            if ($statement->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    

    // Méthode pour obtenir tous les jeux avec les conditions spécifiées
    public static function getSpecificGames($userId) {
        try {
            $dbh = self::connexionDB();
    
            $query = "
                SELECT DISTINCT games.id as number, type, level, category, league, receiving_id as receivingTeamId, 
                       r.name as receivingTeamName, visiting_id as visitingTeamId, v.name as visitingTeamName, 
                       location as place, venue, moment
                FROM games 
                LEFT JOIN signatures ON games.id = signatures.game_id 
                INNER JOIN teams r ON games.receiving_id = r.id 
                INNER JOIN teams v ON games.visiting_id = v.id
                WHERE signatures.user_id = :user_id OR signatures.game_id IS NULL
            ";
    
            $statement = $dbh->prepare($query);
            $statement->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $statement->execute();
    
            $res = [];
            while ($rec = $statement->fetch()) {
                $game = new Game($rec);
                $game->scoreReceiving = 0;
                $game->scoreVisiting = 0;
                foreach (self::getSets($game) as $set) {
                    if (self::setIsOver($set)) {
                        if ($set->scoreReceiving > $set->scoreVisiting) {
                            $game->scoreReceiving++;
                        } else {
                            $game->scoreVisiting++;
                        }
                    }
                }
                $res[] = $game;
            }
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }
    
    public static function getOtherGames($userId) {
        try {
            $dbh = self::connexionDB();
    
            $query = "
                SELECT DISTINCT games.id as number, type, level, category, league, receiving_id as receivingTeamId, 
                        r.name as receivingTeamName, visiting_id as visitingTeamId, v.name as visitingTeamName, 
                        location as place, venue, moment
                FROM games 
                INNER JOIN signatures s1 ON games.id = s1.game_id
                INNER JOIN teams r ON games.receiving_id = r.id 
                INNER JOIN teams v ON games.visiting_id = v.id
                WHERE games.id NOT IN (
                    SELECT game_id 
                    FROM signatures 
                    WHERE user_id = :user_id
                )
            ";
    
            $statement = $dbh->prepare($query);
            $statement->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $statement->execute();
    
            $res = [];
            while ($rec = $statement->fetch()) {
                $game = new Game($rec);
                $game->scoreReceiving = 0;
                $game->scoreVisiting = 0;
                foreach (self::getSets($game) as $set) {
                    if (self::setIsOver($set)) {
                        if ($set->scoreReceiving > $set->scoreVisiting) {
                            $game->scoreReceiving++;
                        } else {
                            $game->scoreVisiting++;
                        }
                    }
                }
                $res[] = $game;
            }
            return $res;
        } catch (PDOException $e) {
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }
    
    public static function hasMarkerRoleInGame($gameId) {
        try {
            $db = self::connexionDB();
            
            $query = "SELECT EXISTS (
                        SELECT 1
                        FROM signatures s
                        JOIN roles r ON s.role_id = r.id
                        WHERE s.game_id = :game_id AND r.name = 'marqueur'
                    ) AS has_marker";
            
            $statement = $db->prepare($query);
            $statement->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            
            if ($statement->execute()) {
                $result = $statement->fetch(PDO::FETCH_ASSOC);
                return (bool) $result['has_marker'];
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function hasArbitreRoleInGame($gameId) {
        try {
            $db = self::connexionDB();
            
            $query = "SELECT EXISTS (
                        SELECT 1
                        FROM signatures s
                        JOIN roles r ON s.role_id = r.id
                        WHERE s.game_id = :game_id AND r.name = 'arbitre'
                    ) AS has_marker";
            
            $statement = $db->prepare($query);
            $statement->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            
            if ($statement->execute()) {
                $result = $statement->fetch(PDO::FETCH_ASSOC);
                return (bool) $result['has_marker'];
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function updateSignature($user_id, $game_id,$token) {
        try {
            $db = self::connexionDB();
            
            $query = "UPDATE signatures SET token_signature = :token 
                      WHERE user_id = :user_id AND game_id = :game_id";
            
            $statement = $db->prepare($query);
            $statement->bindParam(':token', $token, PDO::PARAM_STR);
            $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $statement->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            
            if ($statement->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function removeToken($game_id){
        try {
            $db = self::connexionDB();
            
            $query = "UPDATE signatures SET token_signature = NULL 
                      WHERE game_id = :game_id";
            
            $statement = $db->prepare($query);
            $statement->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            
            if ($statement->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function gameIsValidate($game_id, $role_name) {
        try {
            $db = self::connexionDB();
            
            $query = "SELECT COUNT(*) as total
                      FROM signatures s
                      JOIN roles r ON s.role_id = r.id
                      WHERE s.game_id = :game_id AND r.name = :role_name AND s.token_signature IS NOT NULL";
            
            $statement = $db->prepare($query);
            $statement->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            $statement->bindParam(':role_name', $role_name, PDO::PARAM_STR);
            
            if ($statement->execute()) {
                $result = $statement->fetch(PDO::FETCH_ASSOC);
                return $result['total'] > 0;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    

    public static function getSignaturesbyGameId($game_id){
        try {
            $dbh = self::connexionDB();
    
            $query = "SELECT * FROM signatures WHERE game_id = :game_id";
            
            $statement = $dbh->prepare($query);
            $statement->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            $statement->execute();
    
            $queryResult = $statement->fetchAll(PDO::FETCH_ASSOC);

            $dbh = null;
    
            return $queryResult;
        } catch (PDOException $e) {
            return null;
        }
    }
       
    public static function getArbitres() {
        try {
            // Connexion à la base de données
            $dbh = self::connexionDB();
            
            // Requête pour récupérer les utilisateurs avec le rôle d'arbitre
            $query = "
                SELECT users.id, users.username, users.email 
                FROM users 
                INNER JOIN roles ON users.role_id = roles.id 
                WHERE roles.name = 'arbitre'
            ";
            
            // Préparation et exécution de la requête
            $statement = $dbh->prepare($query);
            $statement->execute();
    
            // Récupération des résultats
            $queryResult = $statement->fetchAll(PDO::FETCH_ASSOC);
    
            // Fermeture de la connexion
            $dbh = null;
    
            return $queryResult;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function getRoleNotValidate($gameId) {
        try {
            // Connexion à la base de données
            $dbh = self::connexionDB();
    
            // Requête pour compter le nombre de signatures avec un token pour un jeu spécifique
            $query = "
                SELECT COUNT(*) as token_count
                FROM signatures
                WHERE game_id = :game_id AND token_signature IS NOT NULL
            ";
    
            // Préparation et exécution de la requête
            $statement = $dbh->prepare($query);
            $statement->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $statement->execute();
    
            // Récupération du résultat
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            $tokenCount = $result['token_count'];
    
            // Déterminer le role_id basé sur le nombre de tokens
            if ($tokenCount == 0) {
                return 2;
            } elseif ($tokenCount == 1) {
                return 3;
            } else {
                return null; // Ou une autre valeur par défaut si nécessaire
            }
        } catch (PDOException $e) {
            // Gérer les erreurs
            print 'Error!:' . $e->getMessage() . '<br/>';
            return null;
        }
    }
    
    
    
    
    
    
    
}


?>
