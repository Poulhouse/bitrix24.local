<?php namespace KPLab\DiadocApi;

class MessageToPost {

	public $FromBoxId;
	public $ToBoxId;
	public $DocumentAttachments;

	public function __construct($FromBoxId, $ToBoxId) {
		$this->FromBoxId = $FromBoxId;
		$this->ToBoxId = $ToBoxId;
	}

	public function Add($documentAttachment) {
		//$DocumentAttachments[] = self::GetDocumentAttachments();
		$this->DocumentAttachments[] = $documentAttachment;
		return $this->DocumentAttachments;
	}

	public function Get() {
		self::GetDocumentAttachments();
		return $this;
	}
	public function GetDocumentAttachments() {
		return $this->DocumentAttachments;
	}

} 
