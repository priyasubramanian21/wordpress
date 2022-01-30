<?php
namespace MailPoetVendor;
if (!defined('ABSPATH')) exit;
class Swift_Image extends Swift_EmbeddedFile
{
 public function __construct($data = null, $filename = null, $contentType = null)
 {
 parent::__construct($data, $filename, $contentType);
 }
 public static function fromPath($path)
 {
 return (new self())->setFile(new Swift_ByteStream_FileByteStream($path));
 }
}