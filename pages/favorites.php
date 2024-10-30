<?php
if(!function_exists('metamatic_is_admin')) {
    return;
}
$admin = metamatic_is_admin();

?>
<div class="metamatic">
    <ul class="assets">
        <?php
        $assets = metamatic_get_asset_list(array('order' => 'title'));
        foreach ($assets as $ass) {
        ?><li><?php if($admin) {
            ?><input type="checkbox" name="ass_<?php echo $ass->aid ?>"><?php
        } ?><?php echo Asset::getLink($ass) ?></li> <?php
        }
        ?>
    </ul>
    <?php if($admin) { ?>
    <div>
        <button onclick="addSite()" type="button"><img alt="+" src="<?php echo metamatic_get_style_dir() ?>/add.png"> <?php metamatic_e('Add to favorites...') ?></button>
        <button onclick="importFriendsFrom()" type="button"><img alt="+" src="<?php echo metamatic_get_style_dir() ?>/add.png"> <?php metamatic_e('Import from site...') ?></button>
        <br>
        <button onclick="selectAll()"><img alt="*" src="<?php echo metamatic_get_style_dir() ?>/select.png"> <?php metamatic_e('Select all') ?></button>
        <button id="deleteSites" onclick="deleteSelected()"><img alt="X" src="<?php echo metamatic_get_style_dir() ?>/delete.png"> <?php metamatic_e('Delete selected') ?></button>
    </div>
    <?php } ?>
    <?php metamatic_logo() ?>
</div>
<?php if($admin) { ?>

<div id="addDialog" style="display: none; width: 100%; text-align: left; padding: 12px;">
    <?php metamatic_e('Enter site address here:') ?><br/>
    <input class="siteAddress" type="text" style="width: 100%"/><br>
    <p class="message"></p>

    <div class="buttons" style="position: absolute; bottom: 12px; right: 12px;">
        <button class="ok">OK</button>
        <button class="cancel"><?php metamatic_e('Cancel')?></button>
    </div>
</div>

<script type="text/javascript">
function initAddDialog() {
    var dlg = jQuery('#addDialog');
    dlg.data('dialogNr', 1);
    jQuery('.cancel', dlg).click(function(){
        dlg.dialog('close');
    });
    jQuery('.siteAddress', dlg).keypress(function(event) {
        if(event.keyCode == 13) {
            // User hit Enter key
            beginAddSite();
        }
    });
    jQuery('.ok', dlg).click(function() {
        beginAddSite();
    });

    function beginAddSite() {
        var value = jQuery('.siteAddress', dlg).val();
        if(value.length < 1) {
            jQuery('.message', dlg).text('<?php metamatic_e('Enter web site address, please')?>');
        } else {
            jQuery('.ok', dlg).attr('disabled', 1);
            jQuery('.message', dlg).text('<?php metamatic_e('Please wait...')?>');
            var dialogNr = dlg.data('dialogNr');
            var methodName = dlg.data("actionMethodName");
            invokeSiteMethod(methodName, value, function(ok, message) {
                if(dlg.data('dialogNr') != dialogNr) {
                    // The dialog has been closed and then reopened.
                    // Silently swallow the result.
                    return;
                }
                if(ok) {
                    // Operation has succeeded.
                    dlg.dialog('close');
                    refreshPage();
                } else {
                    // Unlock the OK button. Operation failed.
                    jQuery('.ok', dlg).removeAttr('disabled');
                    jQuery('.message', dlg).text(message);
                }
            });
        }
    }
    dlg.dialog({
        title: 'Untitled',
        modal: true,
        resizable: false,
        autoOpen: false
    });
}

function openSiteDialog(title, methodName) {
    var dlg = jQuery('#addDialog');
    jQuery('.siteAddress', dlg).val('');
    jQuery('.message', dlg).text('');
    jQuery('.ok', dlg).removeAttr('disabled');

    // Increment dialog sequence number:
    dlg.data('dialogNr', dlg.data('dialogNr') + 1);
    dlg.dialog('option', 'title', title);
    dlg.data('actionMethodName', methodName);
    dlg.dialog('open');
    jQuery('.siteAddress', dlg).focus();
}

function addSite() {
    openSiteDialog('<?php metamatic_e('Add site to favorites')?>', 'siteFavorite');
}
function importFriendsFrom() {
    openSiteDialog('<?php metamatic_e('Import friends')?>', 'importFavoritesFromSite');
}
function invokeSiteMethod(methodName, addr, callback) {
    var params = {
        method: methodName,
        args: JSON.stringify({
            url: addr,
            discoverFeedUrl: true // Ask server to dig into the site and obtain feed URL, if possible
        })
    };
    jQuery.ajax({
        url: '<?php echo metamatic_get_isite_url() ?>',
        type: 'post',
        dataType: 'text',
        data: params,
        success: function(resp) {
            var pos = resp.indexOf('}<!--');
            if(pos >= 0) {
                resp = resp.substring(0, pos + 1);
            }
            try {
                resp = JSON.parse(resp);
            } catch(e) {
            }
            var ok = resp && (resp.result == 'ok');
            var message = resp? resp.error: '<?php echo metamatic_e('Failed to add site...') ?>';
            callback(ok, message);
        },
        error: function() {
            callback(false, '<?php metamatic_e('Communication to server failed.')?>');
        }
    });
}
function selectionChanged() {
    var count = jQuery('ul.assets input:checked').length;
    var butts = jQuery('#deleteSites');
    if(count > 0) {
        butts.removeAttr('disabled');
    } else {
        butts.attr('disabled', 1);
    }
}

function deleteSelected() {
    var selected = jQuery('ul.assets input:checked');
    if(selected.length < 1) {
        return;
    }
    var count = selected.length;
    var ids = [];
    selected.each(function() {
        var id = jQuery(this).attr('name').substring(4);
        ids.push(id);
    });

    showDialog(
        '<?php metamatic_e('Are you sure you want to delete %d favorites?')?>',
        '<?php metamatic_e('Delete favorites')?>',
        [count],
        ['OK', '<?php metamatic_e('Cancel')?>'],
        function(buttonIndex) {
            if(buttonIndex == 0) {
                doUnfavorite(ids);
            }
        }
    );
}

function doUnfavorite(ids) {
    var params = {
        method: 'unfavoriteSites',
        args: JSON.stringify(ids)
    };
    jQuery.ajax({
        url: '<?php echo metamatic_get_isite_url() ?>',
        type: 'post',
        dataType: 'json',
        data: params,
        success: function() {
            refreshPage();
        },
        error: function() {
            refreshPage();
        }
    });
}

function selectAll() {
    jQuery('ul.assets input[type=checkbox]').attr('checked', 1);
    selectionChanged();
}

function refreshPage() {
    window.location.reload();
}

jQuery(document).ready(function() {
    jQuery('ul.assets input[type=checkbox]').click(selectionChanged);
    initAddDialog();
    selectionChanged();
});
</script>
<?php } ?>