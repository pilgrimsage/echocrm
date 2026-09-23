<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">{vtranslate('LBL_UPLOAD_MODULE', $QUALIFIED_MODULE)}</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">

<form id="myForm" action="index.php?module={$MODULE}&action=UploadFile&parent=Settings" method="post" enctype="multipart/form-data">
	<input type="file" size="60" name="myfile">
	<input type="submit" class="btn btn-success" value="{vtranslate('LBL_UPLOAD', $QUALIFIED_MODULE)}">
</form>

<div id="progress" style="display:none;">
	<div class="progress">
		<div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div>
	</div>
	<div id="percent">0%</div>
</div>
<br/>

<div id="message"></div>

</div><!-- /modal-body -->

<script type="text/javascript">
jQuery(function($) {
	$('#myForm').on('submit', function(e) {
		e.preventDefault();

		var form = this;
		var fileInput = form.myfile;
		if (!fileInput.files.length) {
			return;
		}

		var formData = new FormData(form);
		var xhr = new XMLHttpRequest();

		$('#progress').show();
		$('#bar').css('width', '0%');
		$('#percent').text('0%');
		$('#message').empty();

		xhr.upload.addEventListener('progress', function(event) {
			if (event.lengthComputable) {
				var percent = Math.round((event.loaded / event.total) * 100);
				$('#bar').css('width', percent + '%');
				$('#percent').text(percent + '%');
			}
		});

		xhr.addEventListener('load', function() {
			var data;
			try {
				data = JSON.parse(xhr.responseText);
			} catch (ex) {
				$('#message').html('<span class="text-danger">{vtranslate("LBL_ERROR_UPLOAD_FILE", $QUALIFIED_MODULE)}</span>');
				return;
			}

			if (data.success) {
				$('#bar').css('width', '100%');
				$('#percent').text('100%');
				md_loadModule(data.result.file, true);
				md_closePopup();
			} else {
				$('#message').html('<span class="text-danger">' + (data.error && data.error.message ? data.error.message : '') + '</span>');
			}
		});

		xhr.addEventListener('error', function() {
			$('#message').html('<span class="text-danger">{vtranslate("LBL_ERROR_UPLOAD_FILE", $QUALIFIED_MODULE)}</span>');
		});

		xhr.open('POST', form.action, true);
		xhr.send(formData);
	});
});
</script>
</div><!-- /modal-content -->
</div><!-- /modal-dialog -->
