<div id="main">
</div>
<script>
    var urlSend = '<?= $controller->url_for('index/send') ?>';
    var urlLoadMessages = '<?= $controller->url_for('index/loadMessages') ?>';
    var urlLoadUsername = '<?= $controller->url_for('index/nameFromUsername') ?>';
    STUDIP.conversations.lastUpdate = <?= time() ?>;
    STUDIP.conversations.startingPoint = <?= $start ?>;
</script>