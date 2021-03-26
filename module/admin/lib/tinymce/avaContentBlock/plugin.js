tinymce.PluginManager.add( "avaContentBlock", function(editor) {
    function onAction() {
        var url = "../snippet_generator/?bare=1";
        var selectedNode = editor.selection.getNode();
        if ( selectedNode.nodeName == "AVACONTENTBLOCK" )
        {
            url += "&op=edit&id=" + selectedNode.getAttribute( "id" );
        }
        editor.windowManager.openUrl( {
            title: "Insert/edit content block",
            url: url,
            width: 800,
            height: 600
        } );
    }

    // Add button
    editor.ui.registry.addButton( "avaContentBlock", {
        title: "Insert/edit content block",
        icon: "template",
        onAction: onAction
    } );

    // Add menubar item
    editor.ui.registry.addMenuItem( "avaContentBlock", {
        text: "Content block",
        icon: "template",
        context: "insert",
        onAction: onAction
    });
} );