<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 5.0//EN" "http://www.w3.org/TR/html5/strict.dtd">
<html>
 <head></head>
 <body>
  <div id="headerblock" class="fixed mimeHeaders mimeHeadersPrint">
<?php foreach ($this->headers as $v): ?>
   <div>
    <strong><?php echo $this->h($v['header']) ?>:</strong>
    <?php echo $this->h($v['value']) ?>
   </div>
<?php endforeach; ?>
  </div>
 </body>
</html>
