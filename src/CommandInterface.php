<?php

namespace Capetown\Core;

interface CommandInterface {
	public static function createDefault(KeybaseAPIClient $keybaseAPIClient): CommandInterface;
	public static function getName(): string;
	
	public function handleMessage(Message $messages):void;
}