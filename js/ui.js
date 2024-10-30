function showDialog(text, title, parameters, buttons, callback, content) {
    if(!buttons) {
        buttons = ["OK"];
    }
    if(!title) {
        title = "(untitled)";
    }

    var dlg = jQuery('<div><p class="message"></p><div class=content></div><div class="buttons" style="position: absolute; bottom: 12px; right: 12px;"></div></div>');
    jQuery(".message", dlg).html(jQuery.sprintf(text, parameters));
    var butts = jQuery(".buttons", dlg);
    for (i in buttons) {
        var butText = buttons[i];
        var butt = jQuery("<button/>").html(butText);
        butts.append(butt);
        butts.append(' ');
        //butt.button();
        butt.attr("index", i);
        butt.click(function() {
            var index = jQuery(this).attr("index");
            var stayOpen = false;
            if(callback) {
                stayOpen = callback(index, dlg);
            }
            if(stayOpen) {
            } else {
                dlg.dialog('close');
            }
        })
    }

    if(content) {
        var jqContent = jQuery(content);
        jqContent.remove();
        jqContent.css('display', 'block');
        jQuery(".content", dlg).append(jqContent);
    }

    var params = {
        title: title,
        modal: true,
        resizable: false,
        autoOpen: true,
        close: function(event, ui) {
            jQuery(dlg).remove();
        }
    };
    dlg.dialog(params);
}