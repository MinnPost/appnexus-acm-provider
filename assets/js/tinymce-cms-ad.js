(function() {
    tinymce.PluginManager.add( 'cms_ad', function( editor, url ) {
    	var shortcode = /\[([^:]+):([^:\]]+)\]/g;

    	//helper functions
        function getAttr(s, n) {
            n = new RegExp(n + '=\"([^\"]+)\"', 'g').exec(s);
            return n ?  window.decodeURIComponent(n[1]) : '';
        };

		function ifShortcode( content ) {
			return content.search( shortcode ) !== -1;
		}

		function replaceShortcodes( content ) {
			return content.replace( shortcode, function( match, p1, p2 ) {
				return html( match, p1, p2 );
			});
		}

		function restoreShortcodes( content ) {
			return content.replace( /(?:<p(?: [^>]+)?>)*(<img [^>]+>)(?:<\/p>)*/g, function( match, image ) {
            	var shortcode = getAttr( image, 'data-shortcode' );
            	if ( shortcode ) {
            		var type = getAttr( image, 'data-shortcode-type' );
            		return '[' + shortcode + ':' + type + ']';
            	}
            	return match;
            });
		}

		function html( data, shortcode, type ) {
			var markup = '<p><img src="' + url + '/../img/' + shortcode + '.png"  class="mceItem mceAdShortcode mceAdShortcode' + type + '" ' + 'data-shortcode="' + shortcode + '" data-shortcode-type="' + type + '" data-mce-resize="false" data-mce-placeholder="1"></p>';
        	return markup;
    	}

		editor.on( 'BeforeSetContent', function( event ) {
			// No shortcodes in content, return.
			if ( ! ifShortcode( event.content ) ) {
				return;
			}
			event.content = replaceShortcodes( event.content );
		});

		// Display gallery, audio or video instead of img in the element path
		editor.on( 'ResolveName', function( event ) {
			var dom = editor.dom, node = event.target;
			if ( node.nodeName === 'IMG' && dom.getAttrib( node, 'data-shortcode' ) ) {
				if ( dom.hasClass( node, 'mceAdShortcode' ) ) {
					event.name = 'cms_ad';
				}
			}
		});

		editor.on( 'PostProcess', function( event ) {
			if ( event.get ) {
				event.content = restoreShortcodes( event.content );
			}
		});

        // Add Ad to Visual Editor Toolbar
        editor.addButton('cms_ad', {
            title: 'Insert Ad Shortcode',
            cmd: 'cms_ad',
            image: url + '/../img/tinymce-icon.png'
        });

        // Add Command when Button Clicked
		editor.addCommand( 'cms_ad', function() {
		    // Ask the user to enter an ad code
		    var result = prompt('Enter the ad code to insert');
		    if ( ! result ) {
		        // User cancelled - exit
		        return;
		    }
		    if ( 0 === result.length ) {
		        // User didn't enter a code
		        return;
		    }
		    // Insert selected text back into editor as a cms_ad shortcode
		    editor.execCommand( 'mceReplaceContent', false, '[cms_ad:' + result + ']' );
		});

	});
})();
