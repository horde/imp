<div class="prefsSmimeContainer">
    <div class="prefsSmimeHeader">
    <?php if ($this->identities): ?>
        <h3>
            <?php echo _('S/MIME Personal Certificate of Identity') ?>
            <?php echo $this->hordeHelp('imp', 'smime-overview-identities') ?>
        </h3>

        
        <div id="SmimeRelink">
            <!-- please set your smime identity on the smime page -->
            <?php echo $this->relink ?>
        </div>


    <?php else: ?>
        <h3>
            <?php echo _('Your S/MIME Personal Certificate') ?>
            <?php echo $this->hordeHelp('imp', 'smime-overview-personalkey') ?>
        </h3>
    <?php endif; ?>
    </div>

    <?php if ($this->identities): ?>
    <?php else: ?>

    <?php if ($this->notsecure): ?>
    <div>
        <em
            class="prefsSmimeWarning"><?php echo _('S/MIME Personal Certificate support requires a secure web connection.') ?></em>
    </div>
    <?php elseif ($this->has_key): ?>
    <?php if ($this->expiredate): ?>
    <p class="prefsSmimeWarning">
        <?php printf(_('Your S/MIME Personal Certificate has expired on %s at %s.'), $this->expiredate, $this->expiretime) ?>
    </p>
    <?php endif ?>
    <div>
        <table>
            <tr>
                <td>
                    <?php
                        // Show or set an alias for a certificate
                        if($this->aliasexists != false) {
                            printf(_('Current alias for your Certificate Set: %s'), $this->aliasexists);
                        } else {
                            echo _('Set Alias for your certificate: ');
                        }
    ?>
                </td>
                <td>
                    [<?php echo $this->alias ?>]
                </td>
            </tr>
            <tr>
                <td>
                    <?php // if the personal certificate exists in the extra db, show its id
    if($this->privatekeyexits) {
        printf(_('The ID of your Certificate Set: %s'), $this->privatekeyexits);
    } else {
        echo _('The ID of your Sign Certificate Set: <i>not set in database</i>');
    }
    ?>
                </td>
            </tr>
            <tr>
                <td>
                    <?php echo _('Your Public Certificate') ?>:
                </td>
                <td>
                    [<?php echo $this->viewpublic ?>]
                    [<?php echo $this->infopublic ?>]
                </td>
            </tr>
            <tr>
                <td>
                    <?php echo _('Your Private Certificate') ?>:
                </td>
                <td>
                    [<?php echo $this->passphrase ?>]
                    [<?php echo $this->viewprivate ?>]
                </td>
            </tr>
        </table>
    </div>

    <!-- delete button -->
    <p>
        <input type="submit" id="delete_smime_personal" name="delete_smime_personal" class="horde-delete"
            value="<?php echo _('Delete Personal Certificate') ?>" />
        <?php echo $this->hordeHelp('imp', 'smime-delete-personal-certs') ?>
    </p>

    <!-- unset personal smime button -->
    <p>
        <input type="submit" id="unset_smime_personal" name="unset_smime_personal" class="horde-unset"
            value="<?php echo _('Unset Personal Certificate') ?>" />
        <?php echo $this->hordeHelp('imp', 'smime-unset-personal-certs')?>
    </p>

    <?php if ($this->has_sign_key): ?>
    <!-- Secondary Certificates -->
    <div class="prefsSmimeHeader">
        <h3>
            <?php echo _('Your Secondary S/MIME Personal Certificate') ?>
        </h3>
    </div>
    <?php if ($this->expiredate_sign): ?>
    <p class="prefsSmimeWarning">
        <?php printf(_('Your Secondary S/MIME Personal Certificate has expired on %s at %s.'), $this->expiredate_sign, $this->expiretime_sign) ?>
    </p>
    <?php endif ?>
    <div>
        <table>
            <tr>
                <td>
                    <?php
                // Show or set an alias for a certificate
                if($this->signaliasexists != false) {
                    printf (_('Current alias for your Certificate Set: %s'), $this->signaliasexists);
                } else {
                    echo _('Set Alias for your certificate: ');
                }
    ?>
                </td>
                <td>
                    [<?php echo $this->alias_sign ?>]
                </td>
            </tr>
            <tr>
                <td>
                    <?php // if the personal certificate exists in the extra db, show its id
    if($this->signkeyexits) {
        printf (_('The ID of your Sign Certificate Set: %s'), $this->signkeyexits);
    } else {
        echo _('The ID of your Sign Certificate Set: <i>not set in database</i>');
    }?>
                </td>
            </tr>
            <tr>
                <td>
                    <?php echo _('Your Secondary Public Certificate') ?>:
                </td>
                <td>
                    [<?php echo $this->viewpublic_sign ?>]
                    [<?php echo $this->infopublic_sign ?>]
                </td>
            </tr>
            <tr>
                <td>
                    <?php echo _('Your Secondary Private Certificate') ?>:
                </td>
                <td>
                    [<?php echo $this->passphrase_sign ?>]
                    [<?php echo $this->viewprivate_sign ?>]
                </td>
            </tr>
        </table>
    </div>

    <!-- delete secondary button -->
    <p>
        <input type="submit" id="delete_smime_personal_sign" name="delete_smime_personal_sign" class="horde-delete"
            value="<?php echo _('Delete Secondary Personal Certificate') ?>" />
        <?php echo $this->hordeHelp('imp', 'smime-delete-personal-certs') ?>
    </p>

    <!-- unset personal smime button -->
    <p>
        <input type="submit" id="unset_smime_secondary" name="unset_smime_secondary" class="horde-unset"
            value="<?php echo _('Unset Secondary Certificate') ?>" />
        <?php echo $this->hordeHelp('imp', 'smime-unset-secondary-certs')?>
    </p>

    <?php endif ?>
    <?php else: ?>
    <div>
        <em><?php echo _('No personal certificate') ?></em>
    </div>
    <?php endif ?>
    <!-- Import button -->
    <?php if ($this->import): ?>

                
                <div>
                    <p>
                        <input type="submit" name="save" class="horde-default" id="import_smime_personal"
                            value="<?php echo _('Import Personal Certificate') ?>" />
                        <?php echo $this->hordeHelp('imp', 'smime-import-personal-certs') ?>
                    </p>
                </div>
                
            


    <?php endif; ?>
    <?php endif; ?>
</div>
<!-- Extra PrivateKeys from Keystore -->
<?php if ($this->identities): //currently not showing anything: user is refered to add smime on smime-preferences page?>
<?php else: ?>
<div class="prefsSmimeContainer">
        <div class="prefsSmimeHeader">
        <h3>
            <?php echo _('Extra S/MIME Personal Certificates') ?>
            <?php echo $this->hordeHelp('imp', 'smime-overview-extrakeys') ?>
        </h3>
    </div>
    

    <!-- listing extra private keys -->
    <div>
        <?php if (empty($this->viewprivateextras)): ?>
        <em><?php echo _('No extra Privateys in Keystore') ?></em>
        <?php else: ?>
        <table>
            <?php $key = 0;
            $array = $this->viewprivateextras;
            foreach ($array as $countNumber => $keyArray): ?>
            <!-- show alias -->
            <tr>
                <td>
                    <?php echo _('Alias: ') ?><?php if (empty($keyArray['alias'])) {
                        echo '<i>'._('No alias').'</i>';
                    } else {
                        echo $keyArray['alias'];
                    } ?>
                </td>
            </tr>
            <tr>
                <td>
                <?php echo _('Public Certificate: ') ?> [<?php echo $keyArray['publiclink'];
                echo _('View') ?></a>][<?php echo $keyArray['publicinfolink'];
                echo _('Details') ?></a>]
                </td>
            </tr>
            </tr>
            <td>
                <?php echo _('Private Certificate: ') ?> [<?php echo $keyArray['privatelink'];
                echo _('View') ?></a>]

                <!-- set-to-secondary smime button -->
                <label
                    for="set_smime_secondary"><?php echo _('Set this key as a secondary sign certificate:') ?></label>
                <input type="submit" id="set_smime_secondary" name="set_smime_secondary" class="horde-set"
                    value="<?php echo $keyArray['id'] ?>" />
                <?php echo $this->hordeHelp('imp', 'smime-set-secondary-certs')?>

                <!-- set-to-personal smime button -->
                <label for="set_smime_personal"><?php echo _('Set this key as personal certificate:') ?></label>
                <input type="submit" id="set_smime_personal" name="set_smime_personal" class="horde-set"
                    value="<?php echo $keyArray['id'] ?>" />
                <?php echo $this->hordeHelp('imp', 'smime-set-personal-certs')?>

                <!-- delete button -->
                <label for="delete_smime_extra"><?php echo _('Delete Certificate') ?> </label>
                <input type="submit" id="delete_smime_extra" name="delete_smime_extra" class="horde-delete"
                    value="<?php echo $keyArray['id'] ?>" />
                <?php echo $this->hordeHelp('imp', 'smime-delete-extra-certs')?>

            </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
        
        <!-- Import button -->
        <?php if ($this->import): ?>
            
            <?php if ($this->identities): ?>
                
                <div>
                    <p>
                        <input type="submit" name="save" class="horde-default" id="import_extra_smime_identity"
                            value="<?php echo _('Import SMIME Certificate for a new Identity') ?>" />
                        <?php echo $this->hordeHelp('imp', 'smime-import-identity-certs') ?>
                    </p>
                </div>

            <?php else: ?>
                
                <div>
                    <p>
                        <input type="submit" name="save" class="horde-default" id="import_extra_smime_personal"
                            value="<?php echo _('Import Extra Personal Certificate') ?>" />
                        <?php echo $this->hordeHelp('imp', 'smime-import-personal-certs') ?>
                    </p>
                </div>
                
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>