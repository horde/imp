
<div class="prefsContainer item"> 
 
 <div> 
 <?php echo _("Your currently set (default) identity: ") ?>
 </div> 
  
 <div id="default_identity">  
  <b><?php echo $this->defaultIdentity.' ( '.$this->defaultAdres.' )'; ?></b>
  <p>
  <?php echo _("If you want to set the keys for different identity, please change your default-identity first: ") ?>
  <a href="<?php echo $this->linkMailIdentity ?>"><?php echo _("link to preferences") ?></a>.
</p> 
 </div> 

  