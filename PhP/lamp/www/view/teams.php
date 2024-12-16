<?php
$title = 'Equipes';

ob_start();
?>

<h1>Equipes</h1>
<ul>

<?php
foreach ($teams as $team)
{
    echo "<li><a href='?action=teamDetail&teamid=". $team->id . "'>".$team->name."</a></li>";
}
?>
</ul>

<?php
$content = ob_get_clean();
require_once 'gabarit.php';
?>
