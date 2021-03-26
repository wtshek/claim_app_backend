<?php
$stringss = array('Shared','Page-specific');
$folders = $_GET['folders'];
$n=count($folders);
if($n==0) {
?>
	<div class="tabs">
	<div class="tab">Shared</div>
	<div class="panel"><iframe src="{$sets.paths.app_from_doc|escape:'html'}/module/file_admin/filemanager.php?langCode=en" width="100%" height="500"></iframe></div>
</div>
<?php
}
else {
for ($x=0;$x<$n;$x++){
	echo $folders[$x];
?><?php
}
}
?>
