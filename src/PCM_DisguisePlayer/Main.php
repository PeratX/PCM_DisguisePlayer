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
 *
 * This file is a part of PCM_DisguisePlayer
 *
 * 插件定制：ZXDA 插件定制平台
 * 致使用者：本插件是自由软件(遵循GPLv3协议开源)，重新发布请务必注明原作者，谢谢。
 * To PRIMARY STUDENTS: DO NOT LOOK AT THIS, THIS MAY HARM TO YOU!
 * This plugin will work quicker and better with Genisys - Ikaros (创世纪 - 易卡螺丝)
 *
 */

namespace PCM_DisguisePlayer;

use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\entity\Cow;
use pocketmine\entity\Entity;
use pocketmine\entity\Pig;
use pocketmine\entity\Sheep;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use PCM_DisguisePlayer\entity\GenericEntity;
use PCM_DisguisePlayer\listener\EventListener;

class Main extends PluginBase{
	const WORKING_ENVIRONMENT = "Genisys";

	const DISGUISE_TYPE = 0;
	const DISGUISE_BLOCK_ID = 1;
	const DISGUISE_BLOCK_META = 2;
	const DISGUISE_ENTITY_NETWORK_ID = 3;
	const DISGUISE_ENTITY_ID = 4;
	const DISGUISE_LAST_X = 5;
	const DISGUISE_LAST_Y = 6;
	const DISGUISE_LAST_Z = 7;
	const DISGUISE_LAST_LEVEL = 8;

	const DISGUISE_TYPE_NONE = -1;
	const DISGUISE_TYPE_BLOCK = 0;
	const DISGUISE_TYPE_ENTITY = 1;

	private static $obj = null;

	/** @var  Config */
	private $cfg;
	private $cfgdata = [];
	/** @var Player[] */
	private $players = [];
	/** @var Player[][] */
	public $blocks = [];
	/** @var Player[][] */
	public $entities = [];
	/** @var  EventListener */
	private $eventListener;

	/** @var  Vector3 */
	private $tempVector;

	public function onEnable(){
		if($this->getServer()->getName() != self::WORKING_ENVIRONMENT){ //Check if is compatible working env
			$this->getLogger()->error("Incompatible working environment: " . $this->getServer()->getName());
			$this->setEnabled(false);
			return;
		}
		self::$obj = $this;
		@mkdir($this->getDataFolder());
		$this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML, [
			"heldItems" => [
				Item::CARROT,
			],
			"availableBlocks" => [
				Block::TNT,
				Block::WOOD,
				Block::WOOD2,
			],
			"availableEntities" => [
				Cow::NETWORK_ID,
				Pig::NETWORK_ID,
				Sheep::NETWORK_ID,
			],
		]);
		$this->cfgdata = $this->cfg->getAll();
		$this->tempVector = new Vector3(0, 0, 0); //In order to save memory
		$this->eventListener = new EventListener($this);
		foreach($this->getServer()->getLevels() as $level){
			$this->blocks[$level->getFolderName()] = [];
		}
		Entity::registerEntity(GenericEntity::class);
		$this->getServer()->getPluginManager()->registerEvents($this->eventListener, $this);
		$this->getLogger()->notice($this->getDescription()->getName() . " has been enabled.");
	}

	/**
	 * @return Main
	 */
	public static function getInstance(){
		return self::$obj;
	}

	public function getAvailableBlocks() : array{
		return $this->cfgdata["availableBlocks"];
	}

	public function getHeldItems() : array{
		return $this->cfgdata["heldItems"];
	}

	public function getAvailableEntities() : array{
		return $this->cfgdata["availableEntities"];
	}

	public function onDisable(){
		foreach($this->players as $p => $d){
			$this->clearPlayerDisguiseStatus($this->getServer()->getPlayerExact($p));
		}
		$this->players = [];
		$this->blocks = [];
		$this->entities = [];
		$this->cfg->setAll($this->cfgdata);
		$this->cfg->save();
		$this->cfg = null;
		$this->getLogger()->notice($this->getDescription()->getName() . " has been disabled");
	}

	public function getPlayers() : array {
		return $this->players;
	}

	public function getBlocks(Level $level){
		if(isset($this->blocks[$level->getFolderName()])){
			return $this->blocks[$level->getFolderName()];
		}
		return null;
	}

	public function setLastPosition(Player $player, Vector3 $pos, Level $level){
		$name = $player->getName();
		$this->players[$name][self::DISGUISE_LAST_X] = $pos->x;
		$this->players[$name][self::DISGUISE_LAST_Y] = $pos->y;
		$this->players[$name][self::DISGUISE_LAST_Z] = $pos->z;
		$this->players[$name][self::DISGUISE_LAST_LEVEL] = $level;
	}

	/**
	 * @param $name
	 * @return null|Level
	 */
	public function getLastLevel($name){
		if($this->getPlayerDisguiseType($name) != self::DISGUISE_TYPE_NONE){
			return $this->players[strtolower($name)][self::DISGUISE_LAST_LEVEL];
		}
		return null;
	}

	public function updateBlock(Player $player){
		if($this->getPlayerDisguiseType($name = $player->getName()) == self::DISGUISE_TYPE_BLOCK){
			$lastBlock = $this->getLastLevel($name)->getBlock($this->tempVector->setComponents($this->players[strtolower($name)][self::DISGUISE_LAST_X], $this->players[strtolower($name)][self::DISGUISE_LAST_Y], $this->players[strtolower($name)][self::DISGUISE_LAST_Z]));
		//	if($lastBlock->getId() == $this->players[strtolower($name)][self::DISGUISE_BLOCK_ID] and $lastBlock->getDamage() == $this->players[strtolower($name)][self::DISGUISE_BLOCK_META]){
				$this->getLastLevel($name)->setBlock($lastBlock, new Air(), true, false);
		//	}
			if(isset($this->blocks[$player->getLevel()->getFolderName()][$hash = Level::blockHash($lastBlock->x, $lastBlock->y, $lastBlock->z)])){
				unset($this->blocks[$player->getLevel()->getFolderName()][$hash]);
			}
			$pos = $player->add(-0.5, 0, -0.5)->round();
			if($player->getLevel()->getBlock($pos)->getId() === Block::AIR){
				$player->getLevel()->setBlock($pos, Block::get($this->players[strtolower($name)][self::DISGUISE_BLOCK_ID], $this->players[strtolower($name)][self::DISGUISE_BLOCK_META]), true, false);
				$this->blocks[$player->getLevel()->getFolderName()][Level::blockHash($pos->x, $pos->y, $pos->z)] = $player;
			}
			$this->setLastPosition($player, $pos, $player->getLevel());
		}
	}

	public function updateEntity(Player $player){
		if($this->getPlayerDisguiseType($player->getName()) == self::DISGUISE_TYPE_ENTITY
			and isset($this->players[$name = strtolower($player->getName())][self::DISGUISE_ENTITY_ID])
		){
			/** @var GenericEntity $ent */
			$ent = $player->getLevel()->getEntity($this->players[$name = strtolower($player->getName())][self::DISGUISE_ENTITY_ID]);
			//Nothing to do!
		}
	}

	public function createEntity(Player $player){
		if($this->getPlayerDisguiseType($player->getName()) == self::DISGUISE_TYPE_ENTITY
			and isset($this->players[$name = strtolower($player->getName())][self::DISGUISE_ENTITY_NETWORK_ID])
			and !isset($this->players[$name][self::DISGUISE_ENTITY_ID])
		){
			$ent = new GenericEntity($player->chunk, $nbt = new CompoundTag("", [
				"Pos" => new ListTag("Pos", [
					new DoubleTag("", $player->x),
					new DoubleTag("", $player->y),
					new DoubleTag("", $player->z)
				]),
				"Motion" => new ListTag("Motion", [
					new DoubleTag("", 0),
					new DoubleTag("", 0),
					new DoubleTag("", 0)
				]),
				"Rotation" => new ListTag("Rotation", [
					new FloatTag("", $player->yaw),
					new FloatTag("", $player->pitch)
				])
			]), $player, $this->players[$name][self::DISGUISE_ENTITY_NETWORK_ID]);
			$ent->spawnToAll();
			$this->players[$name][self::DISGUISE_ENTITY_ID] = $ent->getId();
			$this->entities[$ent->getId()] = $player;
		}
	}

	public function disguisePlayerToEntity(Player $player, int $network_id){
		if($this->getPlayerDisguiseType($player->getName()) == self::DISGUISE_TYPE_NONE and in_array($network_id, $this->getAvailableEntities())){
			$this->setPlayerDisguiseType($player, self::DISGUISE_TYPE_ENTITY);
			$name = $player->getName();
			$this->players[strtolower($name)][self::DISGUISE_ENTITY_NETWORK_ID] = $network_id;
			$this->setLastPosition($player, $player, $player->getLevel());
			$this->hidePlayer($player);
			$this->createEntity($player);
			$this->updateEntity($player);
		}
	}

	public function disguisePlayerToBlock(Player $player, int $id, int $meta){
		if($this->getPlayerDisguiseType($player) == self::DISGUISE_TYPE_NONE and in_array($id, $this->getAvailableBlocks())){
			$this->setPlayerDisguiseType($player, self::DISGUISE_TYPE_BLOCK);
			$name = $player->getName();
			$this->players[strtolower($name)][self::DISGUISE_BLOCK_ID] = $id;
			$this->players[strtolower($name)][self::DISGUISE_BLOCK_META] = $meta;
			$this->setLastPosition($player, $player, $player->getLevel());
			$this->hidePlayer($player);
			$this->updateBlock($player);
		}
	}

	public function clearPlayerDisguiseStatus(Player $player){
		if(isset($this->players[strtolower($player->getName())])){
			if($this->players[strtolower($player->getName())][self::DISGUISE_TYPE] == self::DISGUISE_TYPE_BLOCK){
				$this->getLastLevel($player->getName())->setBlock(
					$this->tempVector->setComponents($this->players[strtolower($player->getName())][self::DISGUISE_LAST_X], $this->players[strtolower($player->getName())][self::DISGUISE_LAST_Y], $this->players[strtolower($player->getName())][self::DISGUISE_LAST_Z]),
					new Air(),
					true,
					false
				);
			}
			if(isset($this->blocks[$this->getLastLevel($player->getName())->getFolderName()][$hash = Level::blockHash($this->players[strtolower($player->getName())][self::DISGUISE_LAST_X], $this->players[strtolower($player->getName())][self::DISGUISE_LAST_Y], $this->players[strtolower($player->getName())][self::DISGUISE_LAST_Z])])){
				unset($this->blocks[$this->getLastLevel($player->getName())->getFolderName()][$hash]);
			}
			if($this->players[strtolower($player->getName())][self::DISGUISE_TYPE] == self::DISGUISE_TYPE_ENTITY and isset($this->entities[$this->players[strtolower($player->getName())][self::DISGUISE_ENTITY_ID]])){
				$ent = $this->getLastLevel($player->getName())->getEntity($id = $this->players[strtolower($player->getName())][self::DISGUISE_ENTITY_ID]);
				if($ent instanceof GenericEntity){
					$ent->close();
				}
				unset($this->entities[$id]);
			}
			$this->setPlayerDisguiseType($player, self::DISGUISE_TYPE_NONE);
			$this->showPlayer($player);
			unset($this->players[strtolower($player->getName())]);
		}
	}

	public function hidePlayer(Player $player){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			$p->hidePlayer($player);
		}
	}

	public function showPlayer(Player $player){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			$p->showPlayer($player);
		}
	}

	public function setPlayerDisguiseType(Player $player, int $type){
		$this->players[strtolower($player->getName())][self::DISGUISE_TYPE] = $type;
		if($type != self::DISGUISE_TYPE_NONE){
			$player->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INVISIBLE, true);
			$player->setDataProperty(Entity::DATA_SHOW_NAMETAG, Entity::DATA_TYPE_BYTE, 0);
		}else{
			$player->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INVISIBLE, false);
			$player->setDataProperty(Entity::DATA_SHOW_NAMETAG, Entity::DATA_TYPE_BYTE, 1);
		}
	}

	public function getPlayerDisguiseType(string $name) : int{
		if(isset($this->players[strtolower($name)])){
			return $this->players[strtolower($name)][self::DISGUISE_TYPE];
		}
		return self::DISGUISE_TYPE_NONE;
	}
}