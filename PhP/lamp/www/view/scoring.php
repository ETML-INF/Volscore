<?php
$title = 'Score du match '.$game->number;

ob_start();
?>

<div class="row text-center"><h1>Set <?= $set->number ?></h1></div>
<div class="d-flex flex-row justify-content-around">
    <div class="d-flex flex-column order-<?= (($game->toss+$set->number) % 2 == 0) ? 1 : 2 ?>">
        <div class="teamname"><?= $game->receivingTeamName ?></div>
        <div class="setscore"><?= $game->scoreReceiving ?> sets</div>
        <div class="setscore"><?= count($game->receivingTimeouts) ?> timeouts</div>
        <div class="score"><?= $set->scoreReceiving ?></div>
        <div class="d-flex flex-column align-items-center">
            <?php foreach ($receivingPositions as $player) : ?>
                <div class="<?= ($player->id == $nextUp->id ? 'serving' : '') ?>"><?= $player->number ?> <?= $player->last_name ?></div>
            <?php endforeach; ?>
        </div>
        <div class="row actions d-flex flex-column">

            <!-- BTN Point -> Ajoute un point-->
            <form method="post" action="?action=scorePoint">
                <input type="hidden" name="setid" value="<?= $set->id ?>" />
                <input type="hidden" name="receiving" value="1" />
                <input class="col-12 btn btn-success" type="submit" value="Point" />
            </form>

            <div class="d-flex flex-row justify-content-between">
                <a class="btn btn-danger m-2" href="?action=selectBooking&teamid=<?= $game->receivingTeamId ?>&setid=<?= $set->id ?>">
                    Sanctions
                </a>
                <?php if (count($game->receivingTimeouts) < 2) : ?>
                    <a class="btn btn-secondary m-2" href="?action=timeout&teamid=<?= $game->receivingTeamId ?>&setid=<?= $set->id ?>" <?= count($game->receivingTimeouts) > 1 ? "disabled" : "" ?>>
                        Temps Mort
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Annuler le dernier point-->
    <div id="removePoint" class="d-flex flex-column order-<?= (($game->toss+$set->number) % 2 == 0) ? 1 : 2 ?>">
        <div class="row actions d-flex flex-column">

            <!-- Formulaire pour annuler le dernier point -->
            <form method="post" action="?action=removePoint">
                <input type="hidden" name="setid" value="<?= $set->id ?>" />
                <input type="hidden" name="receiving" value="1" />
                <input id="btnRemovePoint" class="col-13 btn btn-warning" type="submit" value="Annuler le dernier point" />
            </form>

        </div>
    </div>

    <div class="d-flex flex-column order-<?= (($game->toss+$set->number) % 2 == 0) ? 2 : 1 ?>">
        <div class="teamname"><?= $game->visitingTeamName ?></div>
        <div class="setscore"><?= $game->scoreVisiting ?></div>
        <div class="setscore"><?= count($game->visitingTimeouts) ?> timeouts</div>
        <div class="score"><?= $set->scoreVisiting ?></div>
        <div class="d-flex flex-column align-items-center">
            <?php foreach ($visitingPositions as $player) : ?>
                <div class="<?= ($player->id == $nextUp->id ? 'serving' : '') ?>"><?= $player->number ?> <?= $player->last_name ?></div>
            <?php endforeach; ?>
        </div>
        <div class="row actions d-flex flex-column">

            <!-- BTN Point Ajoute un point-->
            <form method="post" action="?action=scorePoint">
                <input type="hidden" name="setid" value="<?= $set->id ?>" />
                <input type="hidden" name="receiving" value="0" />
                <input class="col-12 btn btn-success" type="submit" value="Point" />
            </form>
            <div class="d-flex flex-row justify-content-between">
                <a class="btn btn-danger m-2" href="?action=selectBooking&teamid=<?= $game->visitingTeamId ?>&setid=<?= $set->id ?>">
                    Sanctions
                </a>
                <?php if (count($game->visitingTimeouts) < 2) : ?>
                    <a class="btn btn-secondary m-2" href="?action=timeout&teamid=<?= $game->visitingTeamId ?>&setid=<?= $set->id ?>">
                        Temps Mort
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'gabarit.php';
?>

