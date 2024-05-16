<?php
$title = 'Matches';

ob_start();
?>

<h1>Matchs</h1>
<table class="table table-bordered">
    <thead>
        <tr><th>Numéro</th><th>Recevante</th><th>Visiteur</th><th>Score</th><th>Action</th></tr>
    </thead>
    <tbody>
    <?php
    foreach ($otherGames as $game)
    {
        echo "<tr><td>".$game->number."</td><td>".$game->receivingTeamName."</td><td>".$game->visitingTeamName."</td><td>".(($game->scoreReceiving+$game->scoreVisiting) > 0 ? $game->scoreReceiving."-".$game->scoreVisiting : "")."</td><td>";
        echo "</td></tr>";
    }

    foreach ($games as $game)
    {
        echo "<tr><td>".$game->number."</td><td>".$game->receivingTeamName."</td><td>".$game->visitingTeamName."</td><td>".(($game->scoreReceiving+$game->scoreVisiting) > 0 ? $game->scoreReceiving."-".$game->scoreVisiting : "")."</td><td>";
        if ($game->isMarkable() && $rolename == "marqueur") {
            echo "<a href='?action=authUser&id=".$game->number."' class='btn btn-sm btn-primary m-1'>Marquer</a>";
        }
        if ($game->isEditable() && $rolename == "marqueur") {
            echo "<a href='?action=edit&id=".$game->number."' class='btn btn-sm btn-primary m-1'>Modifier</a>";
        }
        if (VolscoreDB::gameIsOver($game) && $rolename == "admin") {
            echo "<a href='?action=sheet&gameid=".$game->number."' class='btn btn-sm btn-primary m-1'>Consulter</a>";
        } elseif (count(VolscoreDB::getSets($game)) > 0 && $rolename == "marqueur") {
            echo "<a href='?action=resumeScoring&gameid=".$game->number."' class='btn btn-sm btn-primary m-1'>Continuer</a>";
        }
        echo "</td></tr>";
    }
    ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
require_once 'gabarit.php';
?>

