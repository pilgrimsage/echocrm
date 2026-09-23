<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">{vtranslate('LBL_MODULES', $QUALIFIED_MODULE)}</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">

<table style="font-size:12px;">
{foreach item=module from=$LIST_MODULES}
<tr><td><a href="javascript:md_selectModule('{$module.name}')">{$module.tablabel}</a></td><td>{vtranslate($module.tablabel, $QUALIFIED_MODULE)}</td></tr>
{/foreach}
</table>
</div><!-- /modal-body -->

<script type="text/javascript">
function md_selectModule(moduleName)
{
	//md_selectDirectoryTemplate(undefined, moduleName);
	md_loadModule(moduleName)
	md_closePopup();
}
</script>
</div><!-- /modal-content -->
</div><!-- /modal-dialog -->
