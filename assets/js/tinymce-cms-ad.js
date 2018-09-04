(function() {
	tinymce.create( 'tinymce.plugins.cms_ad', {

		init : function( ed, url ) {
			var t = this;

			t.url = url;

			//replace shortcode before editor content set
			ed.onBeforeSetContent.add( function( ed, o ) {
				o.content = t._do_cms_ad( o.content );
			});

			//replace shortcode as its inserted into editor (which uses the exec command)
			ed.onExecCommand.add( function( ed, cmd ) {
			    if ( 'mceInsertContent' === cmd ) {
					tinyMCE.activeEditor.setContent( t._do_cms_ad( tinyMCE.activeEditor.getContent() ) );
				}
			});

			//replace the image back to shortcode on save
			ed.onPostProcess.add( function( ed, o ) {
				if ( o.get )
					o.content = t._get_cms_ad( o.content );
			});

			// button to add ads
			ed.addCommand( 'cms_ad', function() {
			    // Ask the user to enter an ad code
			    var result = prompt( 'Enter the ad code to insert' );
			    if ( ! result ) {
			        // User cancelled - exit
			        return;
			    }
			    if ( 0 === result.length ) {
			        // User didn't enter a code
			        return;
			    }
			    // Insert selected text back into editor as a cms_ad shortcode
			    ed.execCommand( 'mceReplaceContent', false, '[cms_ad:' + result + ']' );
			});

			// Add Ad to Visual Editor Toolbar
	        ed.addButton('cms_ad', {
	            title: 'Insert Ad Shortcode',
	            cmd: 'cms_ad',
	            image: url + '/../img/tinymce-icon.png'
	        });

		},

		_do_cms_ad : function( co ) {
			return co.replace(/\[([cms_ad:]+):([^:\]]+)\]/g, function( a, b, c ) {
				return '<img src="/wp-content/plugins/appnexus-acm-provider/assets/img/' + b + '.png" class="mceItem mceAdShortcode mceAdShortcode' + tinymce.DOM.encode( c ) + '" alt="' + tinymce.DOM.encode( b ) + ':' + tinymce.DOM.encode( c ) + '" data-shortcode="' + tinymce.DOM.encode( b ) + '" data-shortcode-type="' + tinymce.DOM.encode( c ) + '" data-mce-resize="false" data-mce-placeholder="1">';
			});
		},

		_get_cms_ad : function( co ) {

			function getAttr( s, n ) {
				n = new RegExp( n + '=\"([^\"]+)\"', 'g' ).exec( s );
				return n ? tinymce.DOM.decode( n[1] ) : '';
			};

			return co.replace( /(?:<p[^>]*>)*(<img[^>]+>)(?:<\/p>)*/g, function( a, im ) {
				var cls = tinymce.trim( getAttr( im, 'class' ) );
				var shortcode = tinymce.trim( getAttr( im, 'data-shortcode' ) );
				var type = tinymce.trim( getAttr( im, 'data-shortcode-type' ) );
				if ( -1 !== cls.indexOf( 'mceAdShortcode' ) && '' !== shortcode && '' !== type ) {
					return '[' + shortcode + ':' + type + ']';
				}
				return a;
			});
		},

		getInfo : function() {
			return {
				longname : 'cms_ad shortcode replace',
				author : 'MinnPost',
				infourl : '',
				version : "1.0"
			};
		}
	});

	tinymce.PluginManager.add( 'cms_ad', tinymce.plugins.cms_ad );
})();
