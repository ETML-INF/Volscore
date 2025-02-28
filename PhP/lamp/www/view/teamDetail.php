<?php

use Symfony\Component\VarDumper\VarDumper;

switch ($_SESSION["languagePreference"]){
    case 'de':
        include("language/de/teamDetailDE.php");
        break;
    case 'it':
        include("language/it/teamDetailIT.php");
        break;
    default:
        include("language/fr/teamDetailFR.php");
        break;   
}

$title = $pageTitle;
ob_start();
?>

<h1><?php echo $team->name?></h1>
<ul>

<?php

foreach ($players as $player)
{
    echo "<li>".$player->first_name." ".$player->last_name."</li>";
}
?>
</ul>

<table class="table table-bordered">
    <thead>
        <tr><th><?php echo $numero?></th><th><?php echo $recevante?></th><th><?php echo $visiteur?></th><th><?php echo $score?></th><th><?php echo $action?></th></tr>
        <tr><th>Moment</th><th>Type</th><th>Ligue</th><th>Recevante</th><th>Visiteur</th><th>Score</th><th>Action</th></tr>
    </thead>
    <tbody>
    <?php
    foreach ($games as $game)
    {
        if  ($game->receivingTeamName == $team->name || $game->visitingTeamName == $team->name){

            echo "<tr><td>".$game->moment."</td><td>".$game->type."</td><td>".$game->league."</td><td>".$game->receivingTeamName."</td><td>".$game->visitingTeamName."</td><td>".(($game->scoreReceiving+$game->scoreVisiting) > 0 ? $game->scoreReceiving."-".$game->scoreVisiting : "")."</td><td>";
        if ($game->isMarkable()) {
            echo "<a href='?action=mark&id=".$game->number."' class='btn btn-sm btn-primary m-1'>". $marquer ."</a>";
        }
        if ($game->isEditable()) {
            echo "<a href='?action=edit&id=".$game->number."' class='btn btn-sm btn-primary m-1'>". $modifier ."</a>";
        }
        if (VolscoreDB::gameIsOver($game)) {
            echo "<a href='?action=sheet&gameid=".$game->number."' class='btn btn-sm btn-primary m-1'>". $consulter ."</a>";
        } elseif (count(VolscoreDB::getSets($game)) > 0) {
            echo "<a href='?action=resumeScoring&gameid=".$game->number."' class='btn btn-sm btn-primary m-1'>". $continuer ."</a>";
        }
        echo "</td></tr>";

        }
        
    }
    ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
require_once 'gabarit.php';
?>
