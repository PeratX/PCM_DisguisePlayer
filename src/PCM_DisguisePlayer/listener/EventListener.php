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

namespace PCM_DisguisePlayer\listener;

use PCM_DisguisePlayer\Main;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;

class EventListener implements Listener{
	/** @var  Main */
	private $plugin;

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}
	
	public function onPlayerJoin(PlayerJoinEvent $ev){
		foreach($this->plugin->getPlayers() as $p){
			if($this->plugin->getPlayerDisguiseType($p) != Main::DISGUISE_TYPE_NONE){
				$ev->getPlayer()->hidePlayer($this->plugin->getServer()->getPlayerExact($p));
			}
		}
	}

	public function onPlayerMove(PlayerMoveEvent $ev){
		$this->plugin->updateBlock($ev->getPlayer());
		//$this->plugin->updateEntity($ev->getPlayer());
	}

	public function onBlockBreak(BlockBreakEvent $ev){
		//if()
	}

	public function onPlayerQuit(PlayerQuitEvent $ev){

	}
	
	public function onLevelLoad(LevelLoadEvent $ev){
		$this->plugin->blocks[$ev->getLevel()->getFolderName()] = [];
	}
	
	public function useItemOn(PlayerInteractEvent $ev){
		if($ev->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			if(in_array($hash = Level::blockHash($ev->getBlock()->x, $ev->getBlock()->y, $ev->getBlock()->z), $blocks = $this->plugin->getBlocks($ev->getBlock()->getLevel()))){
				$player = $blocks[$hash];
				$item = $ev->getItem();
				$damageTable = [
					Item::WOODEN_SWORD => 4,
					Item::GOLD_SWORD => 4,
					Item::STONE_SWORD => 5,
					Item::IRON_SWORD => 6,
					Item::DIAMOND_SWORD => 7,

					Item::WOODEN_AXE => 3,
					Item::GOLD_AXE => 3,
					Item::STONE_AXE => 3,
					Item::IRON_AXE => 5,
					Item::DIAMOND_AXE => 6,

					Item::WOODEN_PICKAXE => 2,
					Item::GOLD_PICKAXE => 2,
					Item::STONE_PICKAXE => 3,
					Item::IRON_PICKAXE => 4,
					Item::DIAMOND_PICKAXE => 5,

					Item::WOODEN_SHOVEL => 1,
					Item::GOLD_SHOVEL => 1,
					Item::STONE_SHOVEL => 2,
					Item::IRON_SHOVEL => 3,
					Item::DIAMOND_SHOVEL => 4,
				];

				$damage = [
					EntityDamageEvent::MODIFIER_BASE => isset($damageTable[$item->getId()]) ? $damageTable[$item->getId()] : 1,
				];

				$armorValues = [
					Item::LEATHER_CAP => 1,
					Item::LEATHER_TUNIC => 3,
					Item::LEATHER_PANTS => 2,
					Item::LEATHER_BOOTS => 1,
					Item::CHAIN_HELMET => 1,
					Item::CHAIN_CHESTPLATE => 5,
					Item::CHAIN_LEGGINGS => 4,
					Item::CHAIN_BOOTS => 1,
					Item::GOLD_HELMET => 1,
					Item::GOLD_CHESTPLATE => 5,
					Item::GOLD_LEGGINGS => 3,
					Item::GOLD_BOOTS => 1,
					Item::IRON_HELMET => 2,
					Item::IRON_CHESTPLATE => 6,
					Item::IRON_LEGGINGS => 5,
					Item::IRON_BOOTS => 2,
					Item::DIAMOND_HELMET => 3,
					Item::DIAMOND_CHESTPLATE => 8,
					Item::DIAMOND_LEGGINGS => 6,
					Item::DIAMOND_BOOTS => 3,
				];
				$points = 0;
				foreach($player->getInventory()->getArmorContents() as $index => $i){
					if(isset($armorValues[$i->getId()])){
						$points += $armorValues[$i->getId()];
					}
				}

				$damage[EntityDamageEvent::MODIFIER_ARMOR] = -floor($damage[EntityDamageEvent::MODIFIER_BASE] * $points * 0.04);

				$event = new EntityDamageByEntityEvent($ev->getPlayer(), $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);

				$player->attack($event->getFinalDamage(), $event);
			}elseif($this->plugin->getPlayerDisguiseType($ev->getPlayer()) == Main::DISGUISE_TYPE_NONE and in_array($ev->getItem()->getId(), $this->plugin->getHeldItems())){
				$block = $ev->getBlock();
				$this->plugin->disguisePlayerToBlock($ev->getPlayer(), $block->getId(), $block->getDamage());
			}
		}
	}
}