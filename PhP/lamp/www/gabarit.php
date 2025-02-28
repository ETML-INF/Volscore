<?php
session_start();

switch ($_SESSION["languagePreference"]){
    case 'de':
        include("language/de/gabaritDE.php");
        break;
    case 'it':
        include("language/it/gabaritIT.php");
        break;
    default:
        include("language/fr/gabaritFR.php");
        break;   
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="/node_modules/bootstrap/dist/css/bootstrap.css">
    <script src="/node_modules/bootstrap/dist/js/bootstrap.js"></script>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/gamesheet.css">
</head>
<body>
<header class="text-center">
    <div></div>
    <a href="/" style="text-decoration:none;"><h1>VolScore</h1></a>
    <form id="languageForm" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="post">
        <label for="language"><?php echo $langue ?>:</label>
        <select onchange="document.getElementById('languageForm').submit()" name="language" id="language">
            <option value="fr"
            <?php if ($_SESSION["languagePreference"] == "fr" || !isset($_SESSION["languagePreference"])){
                echo "selected";
            }?>
            >FR</option>
            <option value="it"
            <?php if ($_SESSION["languagePreference"] == "it"){
                echo "selected";
            }?>
            >IT</option>
            <option value="de"
            <?php if ($_SESSION["languagePreference"] == "de"){
                echo "selected";
            }?>
            >DE</option>
        </select>
    </form>
</header>
<div class="container">
<?= $content ?>
</div>
<footer class="text-center">
    <p>© ETML 2023</p>
</footer>
</body>
</html>
