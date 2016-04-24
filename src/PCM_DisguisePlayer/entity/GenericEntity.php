<?php

/*
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PeratX
 * @link https://mcper.cn
 *
 */

/**
 * This file is a part of PCM_DisguisePlayer
 */

namespace PCM_DisguisePlayer\entity;

use pocketmine\entity\Cow;
use pocketmine\entity\Creature;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\Compound;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

use PCM_DisguisePlayer\Main;

class GenericEntity extends Creature{
	public $width = 0.6;
	public $length = 0.6;
	public $height = 1.8;
	public $eyeHeight = 1.62;

	private $network_id = Cow::NETWORK_ID;

	/** @var  Player */
	private $owner;

	public function getName(){
		return "Generic Entity";
	}

	public function __construct(FullChunk $chunk, Compound $nbt, Player $owner = null, $network_id = 0){
		if($owner == null or $network_id == 0){
			$this->close();
			return;
		}
		$this->owner = $owner;
		$this->network_id = $network_id;
		$this->setDataProperty(self::DATA_NO_AI, self::DATA_TYPE_BYTE, 1);
		parent::__construct($chunk, $nbt);
	}

	public function initEntity(){
		$this->setMaxHealth($this->owner->getMaxHealth());
		$this->setHealth($this->owner->getHealth());
	}

	public function attack($damage, EntityDamageEvent $source){
		$this->owner->attack($damage, $source);
		parent::attack($damage, $source);
	}

	/*public function kill(){
		//$this->owner->kill();
		Main::getInstance()->clearPlayerDisguiseStatus($this->owner);
		parent::kill();
		$this->close();
	}*/

	public function setHealth($amount){
		if($amount > 0){
			parent::setHealth($amount);
		}
	}

	public function onUpdate($tick){
		if(($tick % 2) == 0){
			if($this->level != $this->owner->getLevel()){
				$this->teleport($this->owner);
				$this->spawnToAll();
			}else{
				$this->setPositionAndRotation($this->owner->add(0, -$this->owner->getEyeHeight(), 0), $this->owner->yaw, $this->owner->pitch);
				$this->updateMovement();
			}

			$this->setMaxHealth($this->owner->getMaxHealth());
			$this->setHealth($this->owner->getHealth());
		}
		return true;
	}

	public function saveNBT(){
		$this->namedtag = null;
	}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = $this->network_id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
}