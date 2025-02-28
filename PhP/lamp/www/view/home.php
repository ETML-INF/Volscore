<?php
switch ($_SESSION["languagePreference"]){
    case 'de':
        include("language/de/homeDE.php");
        break;
    case 'it':
        include("language/it/homeIT.php");
        break;
    default:
        include("language/fr/homeFR.php");
        break;   
}

$title = $pageTitle;
ob_start();
?>
<div class="row">
    <div class="col-6">
        <a href="?action=teams">
            <h1><?php echo $equipes ?></h1>
        </a>
    </div>
    <div class="col-6">
        <a href="?action=games">
            <h1><?php echo $matches ?></h1>
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once 'gabarit.php';
?>

