/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
    //vtiger editor toolbar configuration
 		    config.removePlugins = 'save,maximize,magicline,wsc,scayt';
			config.fullPage = true;
 		    config.allowedContent = true;
			config.disableNativeSpellChecker = false;
			config.enterMode = CKEDITOR.ENTER_BR;
			config.shiftEnterMode = CKEDITOR.ENTER_P;
			config.autoParagraph = false;
			config.fillEmptyBlocks = false;
			config.filebrowserBrowseUrl = 'kcfinder/browse.php?type=images';
			config.filebrowserUploadUrl = 'kcfinder/upload.php?type=images';
 		    config.toolbarGroups = [
 		        { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
 		        { name: 'editing', groups: [ 'find', 'selection', 'spellchecker' ] },
				{ name: 'insert' ,groups:['blocks']},
 		        { name: 'links' },
 		        { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
 	        '/',
 		        { name: 'styles' },
 		        { name: 'colors' },
 		        { name: 'tools' },
 		        { name: 'others' },
 		        { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },{name: 'align'},
 	        { name: 'paragraph', groups: [ 'list', 'indent' ] },
            ];

			//Add new custom font names in below array
			var customFonts = ['FreeStyle Script','Brush Script STD','Bradley Hand ITC','Vladimir Script'];
			for(var i = 0; i < customFonts.length; i++){
				  config.font_names = config.font_names+';'+customFonts[i];
			}
};
