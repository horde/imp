<form id="redirect" name="redirect" style="display:none">
 <?php echo $this->hiddenFieldTag('composeCache', null, ['id' => 'composeCacheRedirect']) ?>

 <div class="horde-buttonbar">
  <div class="iconImg headercloseimg closeImg" id="redirect_close"></div>
  <ul><li class="horde-icon">
   <?php echo $this->actionButton(['icon' => 'Redirect', 'id' => 'send_button_redirect', 'title' => _("Redirect")]) ?>
  </li></ul>
 </div>

 <table>
  <tr id="redirect_sendto">
   <td class="label">
    <span><?php echo _("To:") ?></span>
   </td>
   <td>
    <?php echo $this->textAreaTag('redirect_to', null, ['size' => '75x1']) ?>
   </td>
  </tr>
 </table>
</form>
