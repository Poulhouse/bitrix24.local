<?php namespace KPLab\DiadocApi;

class DocumentAttachment {
	public function __construct(
		$TypeNamedId,
		$Content,
		$SignWithTestSignature,
		$NonformalizedDocumentName,
		$CustomDocumentId = "Строковый идентификатор учетной системы",
		$Comment = null
	) {
		$this->TypeNamedId = $TypeNamedId;
		$this->SignedContent = [
			"Content" => $Content,
			"SignWithTestSignature" => $SignWithTestSignature
		];
		$this->NonformalizedDocumentName = $NonformalizedDocumentName;
		$this->CustomDocumentId = $CustomDocumentId;
		$this->Metadata = null;
		$this->Comment = $Comment;
	}
	public function getSignedContent() {
		return $this->SignedContent;
	}
	public function setMetadataItem($Key = "FileName", $Value = null, $keyFileName = false) {
		$Metadata = [];
		if($keyFileName) $metadataItem = ["Key" => $Key, "Value" => $this->NonformalizedDocumentName];
		else $metadataItem = ["Key" => $Key, "Value" => $Value];
		array_push($Metadata, $metadataItem);
		$this->Metadata = $Metadata;
		return true;
	}
	public function get() {

		$ar_DocumentAttachment = [
			"TypeNamedId" => $this->TypeNamedId,
			"SignedContent" => $this->SignedContent,
			"Metadata" => $this->Metadata,
			"CustomDocumentId" => $this->CustomDocumentId,
			"Comment" => $this->Comment
		];

		return $ar_DocumentAttachment;
	}

}