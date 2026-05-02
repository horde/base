<h1 class="header"><?php echo $this->escape(_("Administration")) ?></h1>

<div class="admin-dashboard-grid">
<?php foreach ($this->adminItems as $item): ?>
    <a href="<?php echo $this->escape($item['url']) ?>" class="admin-dashboard-card">
        <img class="admin-card-icon" src="<?php echo $this->escape($item['iconUrl']) ?>" alt="<?php echo $this->escape($item['name']) ?>">
        <span class="admin-card-label"><?php echo $this->escape($item['name']) ?></span>
    </a>
<?php endforeach; ?>
</div>
