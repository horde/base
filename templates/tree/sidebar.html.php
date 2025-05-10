<?php foreach ($this->rootItems as $root): ?>
<?php echo $this->renderPartial('row', ['locals' => $this->items[$root]]) ?>
<?php endforeach ?>
