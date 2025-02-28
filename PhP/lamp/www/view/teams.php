<?php
switch ($_SESSION["languagePreference"]){
    case 'de':
        include("language/de/teamsDE.php");
        break;
    case 'it':
        include("language/it/teamsIT.php");
        break;
    default:
        include("language/fr/teamsFR.php");
        break;   
}

$title = $pageTitle;
ob_start();
?>

<h1><?php echo $equipes ?></h1>
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
