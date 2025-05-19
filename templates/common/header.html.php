<!DOCTYPE html<?php if (!$this->smartmobileView): ?> PUBLIC "-//W3C//DTD HTML 5.0//EN" "http://www.w3.org/TR/html5/strict.dtd"<?php endif; ?>>
<html<?php echo $this->htmlAttr ?>>
 <head>
  <?php if (extension_loaded('newrelic')) {
      echo newrelic_get_browser_timing_header();
  } ?>
  <?php $this->pageOutput->outputMetaTags(); ?>
  <?php $this->pageOutput->includeStylesheetFiles($this->stylesheetOpts, true); ?>
<?php if (!$this->minimalView): ?>
  <?php $this->pageOutput->includeFavicon(); ?>
  <?php $this->pageOutput->outputLinkTags(); ?>
<?php if ($this->outputJs): ?>
  <?php $this->pageOutput->includeScriptFiles(); ?>
  <?php $this->pageOutput->outputInlineScript(); ?>
<?php endif; ?>
<?php endif; ?>
  <title><?php echo $this->pageTitle ?></title>
 </head>

 <body<?php echo $this->bodyAttr ?>>
