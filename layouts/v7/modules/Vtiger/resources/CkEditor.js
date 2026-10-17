/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/**
 * CKEditor 5 wrapper. The editor is created asynchronously, so every consumer goes
 * through the static helpers (getData / insertHtml / onBlur / whenReady) instead of
 * touching an editor object directly. The source textarea is kept in sync on every
 * change, so a plain form serialize still posts the current HTML.
 */
jQuery.Class("Vtiger_CkEditor_Js", {

	instances : {},
	pending : {},

	customFonts : ['FreeStyle Script', 'Brush Script STD', 'Bradley Hand ITC', 'Vladimir Script'],

	toolbars : {
		full : [
			'undo', 'redo', '|', 'findAndReplace', '|', 'heading', '|',
			'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', '|',
			'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'removeFormat', '|',
			'alignment', '|', 'numberedList', 'bulletedList', 'outdent', 'indent', '|',
			'link', 'insertImage', 'insertTable', 'blockQuote', 'horizontalLine', 'specialCharacters', '|',
			'sourceEditing'
		],
		basic : [
			'bold', 'italic', 'underline', '|', 'fontColor', 'fontBackgroundColor', '|',
			'alignment', '|', 'numberedList', 'bulletedList', '|', 'link', 'insertImage', '|',
			'removeFormat', '|', 'fontFamily', 'fontSize', '|', 'sourceEditing'
		]
	},

	get : function(id) {
		return Vtiger_CkEditor_Js.instances[id];
	},

	/**
	 * Promise for the editor of the given textarea id (resolves with undefined if none was loaded)
	 */
	whenReady : function(id) {
		return Vtiger_CkEditor_Js.pending[id] || Promise.resolve(Vtiger_CkEditor_Js.instances[id]);
	},

	/**
	 * Current HTML of the editor, or the textarea value when no editor is attached
	 */
	getData : function(id) {
		var editor = Vtiger_CkEditor_Js.instances[id];
		return editor ? editor.getData() : jQuery('#' + id).val();
	},

	/**
	 * Insert html at the cursor of the editor; false if there is no editor for the id
	 */
	insertHtml : function(id, html) {
		var editor = Vtiger_CkEditor_Js.instances[id];
		if (!editor) {
			return false;
		}
		var selection = editor.model.document.selection;
		var selected = selection.getSelectedElement();
		if (selected && editor.model.schema.isObject(selected) && !editor.ui.focusTracker.isFocused) {
			// a template that starts with a table comes up with the table selected: do not replace it
			editor.model.change(function(writer) {
				writer.setSelection(writer.createPositionAt(editor.model.document.getRoot(), 'end'));
			});
		}
		var fragment = editor.data.toModel(editor.data.processor.toView(html));
		editor.model.insertContent(fragment);
		return true;
	},

	onBlur : function(id, callback) {
		Vtiger_CkEditor_Js.whenReady(id).then(function(editor) {
			if (editor) {
				editor.ui.focusTracker.on('change:isFocused', function(event, name, isFocused) {
					if (!isFocused) {
						callback();
					}
				});
			}
		});
	},

	/**
	 * Destroy the editor of a textarea id; the textarea gets the final HTML back
	 */
	destroy : function(id) {
		var editor = Vtiger_CkEditor_Js.instances[id];
		if (!editor) {
			return Promise.resolve();
		}
		delete Vtiger_CkEditor_Js.instances[id];
		return editor.destroy();
	},

	getEditorConfig : function(customConfig) {
		var C = window.CKEDITOR;
		customConfig = customConfig || {};
		var fonts = ['default', 'Arial, Helvetica, sans-serif', 'Courier New, Courier, monospace', 'Georgia, serif',
			'Lucida Sans Unicode, Lucida Grande, sans-serif', 'Tahoma, Geneva, sans-serif', 'Times New Roman, Times, serif',
			'Trebuchet MS, Helvetica, sans-serif', 'Verdana, Geneva, sans-serif'].concat(Vtiger_CkEditor_Js.customFonts);
		return {
			licenseKey : 'GPL',
			plugins : [
				C.Essentials, C.Paragraph, C.Heading, C.Bold, C.Italic, C.Underline, C.Strikethrough,
				C.Subscript, C.Superscript, C.RemoveFormat, C.Alignment, C.Link, C.AutoLink, C.List,
				C.Indent, C.IndentBlock, C.BlockQuote, C.HorizontalLine, C.SpecialCharacters,
				C.SpecialCharactersEssentials, C.FindAndReplace, C.PasteFromOffice, C.SourceEditing,
				C.FontFamily, C.FontSize, C.FontColor, C.FontBackgroundColor, C.Table, C.TableToolbar,
				C.TableProperties, C.TableCellProperties, C.PlainTableOutput, C.TableLayout, C.Image, C.ImageToolbar, C.ImageStyle,
				C.ImageResize, C.ImageInsert, C.ImageInsertViaUrl, C.ImageUpload, C.Base64UploadAdapter,
				C.LinkImage, C.AutoImage, C.GeneralHtmlSupport, C.HtmlComment, C.FullPage
			],
			toolbar : {
				items : Vtiger_CkEditor_Js.toolbars[customConfig.toolbar] || Vtiger_CkEditor_Js.toolbars.full,
				shouldNotGroupWhenFull : true
			},
			fontFamily : {options : fonts, supportAllValues : true},
			fontSize : {options : [9, 10, 11, 12, 'default', 14, 16, 18, 20, 24, 28, 36, 48], supportAllValues : true},
			table : {contentToolbar : ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties']},
			image : {toolbar : ['imageStyle:inline', 'imageStyle:block', '|', 'toggleImageCaption', 'imageTextAlternative']},
			link : {defaultProtocol : 'https://'},
			// CKEditor 4 ran with allowedContent=true: keep the markup of mail templates untouched
			htmlSupport : {
				allow : [{name : /^.*$/, styles : true, attributes : true, classes : true}],
				disallow : [{name : 'script'}]
			}
		};
	}
}, {

	setElement : function(element) {
		this.element = element;
		return this;
	},

	getElement : function() {
		return this.element;
	},

	getElementId : function() {
		return this.getElement().attr('id');
	},

	getCkEditorInstanceFromName : function() {
		return Vtiger_CkEditor_Js.get(this.getElementId());
	},

	getPlainText : function() {
		var html = Vtiger_CkEditor_Js.getData(this.getElementId());
		return jQuery('<div>').html(html).text();
	},

	/**
	 * Load the editor on a textarea
	 * @params : element - the textarea, customConfig - {height : css size or px number, toolbar : 'full'|'basic'}
	 * @return : promise resolving with the editor
	 */
	loadCkEditor : function(element, customConfig) {
		customConfig = customConfig || {};
		this.setElement(element);
		var id = this.getElementId();
		var textarea = element.get(0);
		var Editor = Vtiger_CkEditor_Js;

		var previous = Editor.whenReady(id).then(function(existing) {
			return existing ? Editor.destroy(id) : null;
		});
		var created = previous.then(function() {
			return window.CKEDITOR.ClassicEditor.create(textarea, Editor.getEditorConfig(customConfig));
		}).then(function(editor) {
			Editor.instances[id] = editor;
			delete Editor.pending[id];
			var height = customConfig.height;
			if (height) {
				height = typeof height === 'number' ? height + 'px' : height;
				editor.editing.view.change(function(writer) {
					writer.setStyle('min-height', height, editor.editing.view.document.getRoot());
				});
			}
			editor.model.document.on('change:data', function() {
				element.val(editor.getData());
			});
			element.val(editor.getData());
			return editor;
		});
		Editor.pending[id] = created;
		created.catch(function(error) {
			delete Editor.pending[id];
			console.error('CKEditor failed to start on #' + id, error);
		});
		return created;
	},

	loadContentsInCkeditor : function(contents) {
		var id = this.getElementId();
		return Vtiger_CkEditor_Js.whenReady(id).then(function(editor) {
			if (editor) {
				editor.setData(contents);
			}
		});
	},

	removeCkEditor : function() {
		if (this.getElement()) {
			return Vtiger_CkEditor_Js.destroy(this.getElementId());
		}
	}
});
