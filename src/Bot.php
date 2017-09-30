<?php

namespace Capetown\Core;

class Bot {
	/**
	 * @var KeybaseAPIClient
	 */
	private $keybaseAPIClient;
	/**
	 * @note these are not actually instances but FQNS, there is no doc syntax for this AFAIK
	 * @var CommandInterface[]
	 */
	private $enabledCommandClasses;
	/**
	 * @var string
	 */
	private $botName;
	
	public function __construct(KeybaseAPIClient $keybaseAPIClient, array $enabledCommands) {
		$this->keybaseAPIClient      = $keybaseAPIClient;
		$this->enabledCommandClasses = $enabledCommands;
		$this->botName               = $keybaseAPIClient->getUsername();
	}
	
	/**
	 * @return CommandInterface[]
	 * @throws \Exception - if there are multiple commands with the same name
	 */
	public function getCommands(): array {
		$commands = [];
		foreach ($this->enabledCommandClasses as $commandClass) {
			if (isset($commands[$commandClass::getName()])) {
				throw new \Exception('Duplicate command name: '.$commandClass::getName());
			}
			
			$commands[$commandClass::getName()] = $commandClass::createDefault($this->keybaseAPIClient);
		}
		
		return $commands;
	}
	
	public function run(): void {
		$loop = \React\EventLoop\Factory::create();
		
		$botName          = $this->botName;
		$keybaseApiClient = $this->keybaseAPIClient;
		$commands         = $this->getCommands();
		$loop->addPeriodicTimer(
			1, function () use ($botName, $keybaseApiClient, $commands) {
			$messagesUnread = $keybaseApiClient->getUnreadMessages();
			foreach ($messagesUnread as $message) {
				$messageParts = explode(' ', $message);
				
				if (count($messageParts) < 2) {
					continue;
				}
				
				$firstWord = array_shift($messageParts);
				if ($firstWord !== '@'.$botName) {
					continue;
				}
				
				$secondWord = array_shift($messageParts);
				if (isset($commands[$secondWord]) === false) {
					$keybaseApiClient->sendMessage(
						$message->getChannel(),
						'Sorry '.$message->getUsername().' I did not understand that'
					);
					continue;
				}
				
				$command = $commands[$secondWord];
				
				$paramsBody            = implode(' ', $messageParts);
				$messageWithParamsBody = new Message(
					$message->getChannel(),
					$message->getUsername(),
					$paramsBody,
					$message->getSentAt()
				);
				
				$command->handleMessage($messageWithParamsBody);
			}
		}
		);
		
		$loop->run();
	}
}