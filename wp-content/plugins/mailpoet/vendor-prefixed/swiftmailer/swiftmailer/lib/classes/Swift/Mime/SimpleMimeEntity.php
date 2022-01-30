<?php
namespace MailPoetVendor;
if (!defined('ABSPATH')) exit;
class Swift_Mime_SimpleMimeEntity implements Swift_Mime_CharsetObserver, Swift_Mime_EncodingObserver
{
 const LEVEL_TOP = 16;
 const LEVEL_MIXED = 256;
 const LEVEL_ALTERNATIVE = 4096;
 const LEVEL_RELATED = 65536;
 private $headers;
 private $body;
 private $encoder;
 private $idGenerator;
 private $boundary;
 private $compositeRanges = ['multipart/mixed' => [self::LEVEL_TOP, self::LEVEL_MIXED], 'multipart/alternative' => [self::LEVEL_MIXED, self::LEVEL_ALTERNATIVE], 'multipart/related' => [self::LEVEL_ALTERNATIVE, self::LEVEL_RELATED]];
 private $compoundLevelFilters = [];
 private $nestingLevel = self::LEVEL_ALTERNATIVE;
 private $cache;
 private $immediateChildren = [];
 private $children = [];
 private $maxLineLength = 78;
 private $alternativePartOrder = ['text/plain' => 1, 'text/html' => 2, 'multipart/related' => 3];
 private $id;
 private $cacheKey;
 protected $userContentType;
 public function __construct(Swift_Mime_SimpleHeaderSet $headers, Swift_Mime_ContentEncoder $encoder, Swift_KeyCache $cache, Swift_IdGenerator $idGenerator)
 {
 $this->cacheKey = \bin2hex(\random_bytes(16));
 // set 32 hex values
 $this->cache = $cache;
 $this->headers = $headers;
 $this->idGenerator = $idGenerator;
 $this->setEncoder($encoder);
 $this->headers->defineOrdering(['Content-Type', 'Content-Transfer-Encoding']);
 // This array specifies that, when the entire MIME document contains
 // $compoundLevel, then for each child within $level, if its Content-Type
 // is $contentType then it should be treated as if it's level is
 // $neededLevel instead. I tried to write that unambiguously! :-\
 // Data Structure:
 // array (
 // $compoundLevel => array(
 // $level => array(
 // $contentType => $neededLevel
 // )
 // )
 // )
 $this->compoundLevelFilters = [self::LEVEL_ALTERNATIVE + self::LEVEL_RELATED => [self::LEVEL_ALTERNATIVE => ['text/plain' => self::LEVEL_ALTERNATIVE, 'text/html' => self::LEVEL_RELATED]]];
 $this->id = $this->idGenerator->generateId();
 }
 public function generateId()
 {
 $this->setId($this->idGenerator->generateId());
 return $this->id;
 }
 public function getHeaders()
 {
 return $this->headers;
 }
 public function getNestingLevel()
 {
 return $this->nestingLevel;
 }
 public function getContentType()
 {
 return $this->getHeaderFieldModel('Content-Type');
 }
 public function getBodyContentType()
 {
 return $this->userContentType;
 }
 public function setContentType($type)
 {
 $this->setContentTypeInHeaders($type);
 // Keep track of the value so that if the content-type changes automatically
 // due to added child entities, it can be restored if they are later removed
 $this->userContentType = $type;
 return $this;
 }
 public function getId()
 {
 $tmp = (array) $this->getHeaderFieldModel($this->getIdField());
 return $this->headers->has($this->getIdField()) ? \current($tmp) : $this->id;
 }
 public function setId($id)
 {
 if (!$this->setHeaderFieldModel($this->getIdField(), $id)) {
 $this->headers->addIdHeader($this->getIdField(), $id);
 }
 $this->id = $id;
 return $this;
 }
 public function getDescription()
 {
 return $this->getHeaderFieldModel('Content-Description');
 }
 public function setDescription($description)
 {
 if (!$this->setHeaderFieldModel('Content-Description', $description)) {
 $this->headers->addTextHeader('Content-Description', $description);
 }
 return $this;
 }
 public function getMaxLineLength()
 {
 return $this->maxLineLength;
 }
 public function setMaxLineLength($length)
 {
 $this->maxLineLength = $length;
 return $this;
 }
 public function getChildren()
 {
 return $this->children;
 }
 public function setChildren(array $children, $compoundLevel = null)
 {
 // TODO: Try to refactor this logic
 $compoundLevel = $compoundLevel ?? $this->getCompoundLevel($children);
 $immediateChildren = [];
 $grandchildren = [];
 $newContentType = $this->userContentType;
 foreach ($children as $child) {
 $level = $this->getNeededChildLevel($child, $compoundLevel);
 if (empty($immediateChildren)) {
 //first iteration
 $immediateChildren = [$child];
 } else {
 $nextLevel = $this->getNeededChildLevel($immediateChildren[0], $compoundLevel);
 if ($nextLevel == $level) {
 $immediateChildren[] = $child;
 } elseif ($level < $nextLevel) {
 // Re-assign immediateChildren to grandchildren
 $grandchildren = \array_merge($grandchildren, $immediateChildren);
 // Set new children
 $immediateChildren = [$child];
 } else {
 $grandchildren[] = $child;
 }
 }
 }
 if ($immediateChildren) {
 $lowestLevel = $this->getNeededChildLevel($immediateChildren[0], $compoundLevel);
 // Determine which composite media type is needed to accommodate the
 // immediate children
 foreach ($this->compositeRanges as $mediaType => $range) {
 if ($lowestLevel > $range[0] && $lowestLevel <= $range[1]) {
 $newContentType = $mediaType;
 break;
 }
 }
 // Put any grandchildren in a subpart
 if (!empty($grandchildren)) {
 $subentity = $this->createChild();
 $subentity->setNestingLevel($lowestLevel);
 $subentity->setChildren($grandchildren, $compoundLevel);
 \array_unshift($immediateChildren, $subentity);
 }
 }
 $this->immediateChildren = $immediateChildren;
 $this->children = $children;
 $this->setContentTypeInHeaders($newContentType);
 $this->fixHeaders();
 $this->sortChildren();
 return $this;
 }
 public function getBody()
 {
 return $this->body instanceof Swift_OutputByteStream ? $this->readStream($this->body) : $this->body;
 }
 public function setBody($body, $contentType = null)
 {
 if ($body !== $this->body) {
 $this->clearCache();
 }
 $this->body = $body;
 if (null !== $contentType) {
 $this->setContentType($contentType);
 }
 return $this;
 }
 public function getEncoder()
 {
 return $this->encoder;
 }
 public function setEncoder(Swift_Mime_ContentEncoder $encoder)
 {
 if ($encoder !== $this->encoder) {
 $this->clearCache();
 }
 $this->encoder = $encoder;
 $this->setEncoding($encoder->getName());
 $this->notifyEncoderChanged($encoder);
 return $this;
 }
 public function getBoundary()
 {
 if (!isset($this->boundary)) {
 $this->boundary = '_=_swift_' . \time() . '_' . \bin2hex(\random_bytes(16)) . '_=_';
 }
 return $this->boundary;
 }
 public function setBoundary($boundary)
 {
 $this->assertValidBoundary($boundary);
 $this->boundary = $boundary;
 return $this;
 }
 public function charsetChanged($charset)
 {
 $this->notifyCharsetChanged($charset);
 }
 public function encoderChanged(Swift_Mime_ContentEncoder $encoder)
 {
 $this->notifyEncoderChanged($encoder);
 }
 public function toString()
 {
 $string = $this->headers->toString();
 $string .= $this->bodyToString();
 return $string;
 }
 protected function bodyToString()
 {
 $string = '';
 if (isset($this->body) && empty($this->immediateChildren)) {
 if ($this->cache->hasKey($this->cacheKey, 'body')) {
 $body = $this->cache->getString($this->cacheKey, 'body');
 } else {
 $body = "\r\n" . $this->encoder->encodeString($this->getBody(), 0, $this->getMaxLineLength());
 $this->cache->setString($this->cacheKey, 'body', $body, Swift_KeyCache::MODE_WRITE);
 }
 $string .= $body;
 }
 if (!empty($this->immediateChildren)) {
 foreach ($this->immediateChildren as $child) {
 $string .= "\r\n\r\n--" . $this->getBoundary() . "\r\n";
 $string .= $child->toString();
 }
 $string .= "\r\n\r\n--" . $this->getBoundary() . "--\r\n";
 }
 return $string;
 }
 public function __toString()
 {
 return $this->toString();
 }
 public function toByteStream(Swift_InputByteStream $is)
 {
 $is->write($this->headers->toString());
 $is->commit();
 $this->bodyToByteStream($is);
 }
 protected function bodyToByteStream(Swift_InputByteStream $is)
 {
 if (empty($this->immediateChildren)) {
 if (isset($this->body)) {
 if ($this->cache->hasKey($this->cacheKey, 'body')) {
 $this->cache->exportToByteStream($this->cacheKey, 'body', $is);
 } else {
 $cacheIs = $this->cache->getInputByteStream($this->cacheKey, 'body');
 if ($cacheIs) {
 $is->bind($cacheIs);
 }
 $is->write("\r\n");
 if ($this->body instanceof Swift_OutputByteStream) {
 $this->body->setReadPointer(0);
 $this->encoder->encodeByteStream($this->body, $is, 0, $this->getMaxLineLength());
 } else {
 $is->write($this->encoder->encodeString($this->getBody(), 0, $this->getMaxLineLength()));
 }
 if ($cacheIs) {
 $is->unbind($cacheIs);
 }
 }
 }
 }
 if (!empty($this->immediateChildren)) {
 foreach ($this->immediateChildren as $child) {
 $is->write("\r\n\r\n--" . $this->getBoundary() . "\r\n");
 $child->toByteStream($is);
 }
 $is->write("\r\n\r\n--" . $this->getBoundary() . "--\r\n");
 }
 }
 protected function getIdField()
 {
 return 'Content-ID';
 }
 protected function getHeaderFieldModel($field)
 {
 if ($this->headers->has($field)) {
 return $this->headers->get($field)->getFieldBodyModel();
 }
 }
 protected function setHeaderFieldModel($field, $model)
 {
 if ($this->headers->has($field)) {
 $this->headers->get($field)->setFieldBodyModel($model);
 return \true;
 }
 return \false;
 }
 protected function getHeaderParameter($field, $parameter)
 {
 if ($this->headers->has($field)) {
 return $this->headers->get($field)->getParameter($parameter);
 }
 }
 protected function setHeaderParameter($field, $parameter, $value)
 {
 if ($this->headers->has($field)) {
 $this->headers->get($field)->setParameter($parameter, $value);
 return \true;
 }
 return \false;
 }
 protected function fixHeaders()
 {
 if (\count($this->immediateChildren)) {
 $this->setHeaderParameter('Content-Type', 'boundary', $this->getBoundary());
 $this->headers->remove('Content-Transfer-Encoding');
 } else {
 $this->setHeaderParameter('Content-Type', 'boundary', null);
 $this->setEncoding($this->encoder->getName());
 }
 }
 protected function getCache()
 {
 return $this->cache;
 }
 protected function getIdGenerator()
 {
 return $this->idGenerator;
 }
 protected function clearCache()
 {
 $this->cache->clearKey($this->cacheKey, 'body');
 }
 private function readStream(Swift_OutputByteStream $os)
 {
 $string = '';
 while (\false !== ($bytes = $os->read(8192))) {
 $string .= $bytes;
 }
 $os->setReadPointer(0);
 return $string;
 }
 private function setEncoding($encoding)
 {
 if (!$this->setHeaderFieldModel('Content-Transfer-Encoding', $encoding)) {
 $this->headers->addTextHeader('Content-Transfer-Encoding', $encoding);
 }
 }
 private function assertValidBoundary($boundary)
 {
 if (!\preg_match('/^[a-z0-9\'\\(\\)\\+_\\-,\\.\\/:=\\?\\ ]{0,69}[a-z0-9\'\\(\\)\\+_\\-,\\.\\/:=\\?]$/Di', $boundary)) {
 throw new Swift_RfcComplianceException('Mime boundary set is not RFC 2046 compliant.');
 }
 }
 private function setContentTypeInHeaders($type)
 {
 if (!$this->setHeaderFieldModel('Content-Type', $type)) {
 $this->headers->addParameterizedHeader('Content-Type', $type);
 }
 }
 private function setNestingLevel($level)
 {
 $this->nestingLevel = $level;
 }
 private function getCompoundLevel($children)
 {
 $level = 0;
 foreach ($children as $child) {
 $level |= $child->getNestingLevel();
 }
 return $level;
 }
 private function getNeededChildLevel($child, $compoundLevel)
 {
 $filter = [];
 foreach ($this->compoundLevelFilters as $bitmask => $rules) {
 if (($compoundLevel & $bitmask) === $bitmask) {
 $filter = $rules + $filter;
 }
 }
 $realLevel = $child->getNestingLevel();
 $lowercaseType = \strtolower($child->getContentType() ?? '');
 if (isset($filter[$realLevel]) && isset($filter[$realLevel][$lowercaseType])) {
 return $filter[$realLevel][$lowercaseType];
 }
 return $realLevel;
 }
 private function createChild()
 {
 return new self($this->headers->newInstance(), $this->encoder, $this->cache, $this->idGenerator);
 }
 private function notifyEncoderChanged(Swift_Mime_ContentEncoder $encoder)
 {
 foreach ($this->immediateChildren as $child) {
 $child->encoderChanged($encoder);
 }
 }
 private function notifyCharsetChanged($charset)
 {
 $this->encoder->charsetChanged($charset);
 $this->headers->charsetChanged($charset);
 foreach ($this->immediateChildren as $child) {
 $child->charsetChanged($charset);
 }
 }
 private function sortChildren()
 {
 $shouldSort = \false;
 foreach ($this->immediateChildren as $child) {
 // NOTE: This include alternative parts moved into a related part
 if (self::LEVEL_ALTERNATIVE == $child->getNestingLevel()) {
 $shouldSort = \true;
 break;
 }
 }
 // Sort in order of preference, if there is one
 if ($shouldSort) {
 // Group the messages by order of preference
 $sorted = [];
 foreach ($this->immediateChildren as $child) {
 $type = $child->getContentType();
 $level = \array_key_exists($type, $this->alternativePartOrder) ? $this->alternativePartOrder[$type] : \max($this->alternativePartOrder) + 1;
 if (empty($sorted[$level])) {
 $sorted[$level] = [];
 }
 $sorted[$level][] = $child;
 }
 \ksort($sorted);
 $this->immediateChildren = \array_reduce($sorted, 'array_merge', []);
 }
 }
 public function __destruct()
 {
 if ($this->cache instanceof Swift_KeyCache) {
 $this->cache->clearAll($this->cacheKey);
 }
 }
 public function __clone()
 {
 $this->headers = clone $this->headers;
 $this->encoder = clone $this->encoder;
 $this->cacheKey = \bin2hex(\random_bytes(16));
 // set 32 hex values
 $children = [];
 foreach ($this->children as $pos => $child) {
 $children[$pos] = clone $child;
 }
 $this->setChildren($children);
 }
 public function __wakeup()
 {
 $this->cacheKey = \bin2hex(\random_bytes(16));
 // set 32 hex values
 $this->cache = new Swift_KeyCache_ArrayKeyCache(new Swift_KeyCache_SimpleKeyCacheInputStream());
 }
}
