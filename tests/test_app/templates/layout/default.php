<?php
/**
 * @var \Cake\View\View $this
 */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>TestApp</title><?= $this->fetch('css') ?></head>
<body>
<?= $this->Flash->render() ?>
<?= $this->fetch('content') ?>
<?= $this->fetch('script') ?>
</body>
</html>
