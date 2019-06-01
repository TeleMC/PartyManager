<?php
namespace PartyManager;

use Core\Core;
use Core\util\Util;
use PartyManager\PartyForm\PartyForm;
use PartyManager\PartyForm\PartySystem\CreatePartyForm;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use UiLibrary\UiLibrary;

class PartyManager extends PluginBase {

    private static $instance = null;
    //public $pre = "§l§b[ §f파티 §b]§r§b";
    public $pre = "§e•";

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->party = new Config($this->getDataFolder() . "Party.yml", Config::YAML);
        $this->pdata = $this->party->getAll();
        $this->member = new Config($this->getDataFolder() . "member.yml", Config::YAML);
        $this->mdata = $this->member->getAll();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->core = Core::getInstance();
        $this->util = new Util($this->core);
        $this->ui = UiLibrary::getInstance();
    }

    public function onDisable() {
        $this->save();
    }

    public function save() {
        $this->party->setAll($this->pdata);
        $this->party->save();
        $this->member->setAll($this->mdata);
        $this->member->save();
    }

    public function isParty($name) {
        if (isset($this->mdata[$name])) return true;
        if (!isset($this->mdata[$name])) return false;
    }

    public function getChatMode($name) {
        if (!isset($this->mdata[$name])) return;
        if ($this->mdata[$name]["파티채팅"] == "on") return true;
        if ($this->mdata[$name]["파티채팅"] == "off") return false;
    }

    public function getPartyMember_($PartyName) {
        return $this->pdata[$PartyName]["파티원"];
    }

    public function giveExp($PartyName, $amount, int $code = 0, int $level = 0) {
        if (!isset($this->pdata[$PartyName])) return;
        if ($this->CountMember($PartyName) == 1) $a = 1;
        if ($this->CountMember($PartyName) == 2) $a = 0.55;
        if ($this->CountMember($PartyName) == 3) $a = 0.4;
        if ($this->CountMember($PartyName) == 4) $a = 0.325;
        foreach ($this->pdata[$PartyName]["파티원"] as $members) {
            $this->util->addExp($members, $amount * $a, $code, $this->util->getLevel($members) - $level);
        }
    }

    public function CountMember($PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        return count($this->pdata[$PartyName]["파티원"]);
    }

    public function getPosition($name) {
        if (!isset($this->mdata[$name])) return;
        return $this->mdata[$name]["직위"];
    }

    public function PartyUI($player) {
        if (!isset($this->mdata[$player->getName()])) {
            if ($player instanceof Player) {
                $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                    if (!is_numeric($data[0])) return;
                    if ($data[0] == 0) {
                        $form = $this->ui->CustomForm(function (Player $player, array $data) {
                            if (!isset($data[1])) {
                                $player->sendMessage("{$this->pre} 파티명을 기입해주세요.");
                                return;
                            }
                            if (isset($this->pdata[$data[1]])) {
                                $player->sendMessage("{$this->pre} 해당 이름의 파티가 이미 존재합니다.");
                                return;
                            }
                            if ($data[2] !== 1) {
                                $search = "비공개";
                            }
                            if ($data[2] == 1) {
                                $search = "공개";
                            }
                            if ($data[3] !== 1) {
                                $join = "자유가입제";
                            }
                            if ($data[3] == 1) {
                                $join = "수락제";
                            }
                            $this->addParty($data[1], $player->getName(), $search, $join);
                            $player->sendMessage("{$this->pre} {$data[1]} 파티가 생성되었습니다!");
                            return;
                        });
                        $form->setTitle("Tele Party");
                        $form->addLabel("파티를 생성합니다.");
                        $form->addInput("파티명", "파티명");
                        $form->addToggle("공개여부");
                        $form->addToggle("수락제 여부");
                        $form->sendToPlayer($player);
                    }

                    if ($data[0] == 1) {
                        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                            $name = $player->getName();
                            if (!is_numeric($data[0])) return;
                            if ($data[0] == 0) {
                                $form = $this->ui->CustomForm(function (Player $player, array $data) {
                                    if (!isset($data[1])) {
                                        $player->sendMessage("{$this->pre} 파티명을 기입해주세요.");
                                        return;
                                    }
                                    if (!isset($this->pdata[$data[1]])) {
                                        $player->sendMessage("{$this->pre} 해당 파티는 존재하지 않습니다.");
                                        return;
                                    }
                                    $this->gname[$player->getName()] = $data[1];
                                    $form = $this->ui->ModalForm(function (Player $player, array $data) {
                                        if ($data[0] == true) {
                                            if (!($this->getServer()->getPlayer($this->getPartyLeader($this->gname[$player->getName()]))) instanceof Player) {
                                                $player->sendMessage("{$this->pre} 해당 파티의 파티장이 접속중이 아닙니다.");
                                                unset($this->gname[$player->getName()]);
                                                return;
                                            }
                                            if ($this->CountMember($this->gname[$player->getName()]) >= 4) {
                                                $player->sendMessage("{$this->pre} 해당 파티 정원이 꽉 찼습니다.");
                                                unset($this->gname[$player->getName()]);
                                                return;
                                            }
                                            /*if(isset($this->mode4[$this->mode3[$name]])){
                                              if(explode(":", $this->mode4[$this->mode3[$name]])[1] !== $player->getName()){
                                                $player->sendMessage("{$this->pre} 파티장이 다른 유저의 가입요청을 받고있습니다. 잠시후에 시도해주세요.");
                                                unset($this->mode2);
                                                unset($this->mode3[$name]);
                                                return;
                                              }
                                            }*/
                                            if (($this->getSearchAccess($this->gname[$player->getName()]) == "공개")) {
                                                if ($this->getJoinAccess($this->gname[$player->getName()]) == "수락제") {
                                                    $this->AccessJoinParty($this->gname[$player->getName()], $player->getName());
                                                    $player->sendMessage("{$this->pre} {$this->gname[$player->getName()]} 파티 가입요청을 보냈습니다.");
                                                    unset($this->gname[$player->getName()]);
                                                    return;
                                                }
                                                $player->sendMessage("{$this->pre} {$this->gname[$player->getName()]} 파티에 가입하였습니다!");
                                                $this->JoinParty($this->gname[$player->getName()], $player->getName());
                                                unset($this->gname[$player->getName()]);
                                                return;
                                            }
                                            $player->sendMessage("{$this->pre} {$this->gname[$player->getName()]} 파티는 초대 외의 방법으로 가입이 불가능합니다.");
                                            unset($this->gname[$player->getName()]);
                                            return;
                                        } else {
                                            unset($this->gname[$player->getName()]);
                                            return;
                                        }
                                    });
                                    $form->setTitle("Tele Party");
                                    $form->setContent("\n§l{$this->gname[$player->getName()]} 파티에 가입하시겠습니까?");
                                    $form->setButton1("§l§8[예]");
                                    $form->setButton2("§l§8[아니오]");
                                    $form->sendToPlayer($player);
                                });
                                $form->setTitle("Tele Party");
                                $form->addLabel("파티를 검색합니다.");
                                $form->addInput("파티명", "파티명");
                                $form->sendToPlayer($player);
                            }
                            $n = 0;
                            foreach ($this->pdata as $party => $pname) {
                                if ($this->getSearchAccess($this->pdata[$party]["파티"]) == "공개") {
                                    $n++;
                                    $this->mode2[$name][$n] = "{$this->pdata[$party]["파티"]}";
                                }
                            }
                            if (!isset($this->mode2[$name][$data[0]])) return;
                            $this->mode3[$name] = $this->mode2[$name][$data[0]];
                            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                                $name = $player->getName();
                                if ($data[0] == true) {
                                    if (!($this->getServer()->getPlayer($this->getPartyLeader($this->mode3[$name]))) instanceof Player) {
                                        $player->sendMessage("{$this->pre} 해당 파티의 파티장이 접속중이 아닙니다.");
                                        unset($this->mode2[$name]);
                                        unset($this->mode3[$name]);
                                        return;
                                    }
                                    if ($this->CountMember($this->mode3[$name]) >= 4) {
                                        $player->sendMessage("{$this->pre} 해당 파티 정원이 꽉 찼습니다.");
                                        unset($this->mode2[$name]);
                                        unset($this->mode3[$name]);
                                        return;
                                    }
                                    /*if(isset($this->mode4[$this->mode3[$name]])){
                                      if(explode(":", $this->mode4[$this->mode3[$name]])[1] !== $player->getName()){
                                        $player->sendMessage("{$this->pre} 파티장이 다른 유저의 가입요청을 받고있습니다. 잠시후에 시도해주세요.");
                                        unset($this->mode2);
                                        unset($this->mode3[$name]);
                                        return;
                                      }
                                    }*/
                                    if (($this->getSearchAccess($this->mode3[$name]) == "공개")) {
                                        if ($this->getJoinAccess($this->mode3[$name]) == "수락제") {
                                            $this->AccessJoinParty($this->mode3[$name], $player->getName());
                                            $player->sendMessage("{$this->pre} {$this->mode3[$name]} 파티 가입요청을 보냈습니다.");
                                            unset($this->mode2[$name]);
                                            unset($this->mode3[$name]);
                                            return;
                                        }
                                        $player->sendMessage("{$this->pre} {$this->mode3[$name]} 파티에 가입하였습니다!");
                                        $this->JoinParty($this->mode3[$name], $player->getName());
                                        unset($this->mode2[$name]);
                                        unset($this->mode3[$name]);
                                        return;
                                    }
                                    $player->sendMessage("{$this->pre} {$this->mode3[$name]} 파티는 초대 외의 방법으로 가입이 불가능합니다.");
                                    unset($this->mode2[$name]);
                                    unset($this->mode3[$name]);
                                    return;
                                } else {
                                    unset($this->mode2[$name]);
                                    unset($this->mode3[$name]);
                                    return;
                                }
                            });
                            $form->setTitle("Tele Party");
                            $form->setContent("\n§l{$this->mode3[$name]} 파티에 가입하시겠습니까?");
                            $form->setButton1("§l§8[예]");
                            $form->setButton2("§l§8[아니오]");
                            $form->sendToPlayer($player);
                        });
                        $form->setTitle("Tele Party");
                        $form->setContent("");
                        $form->addButton("§l파티 검색\n§r§8파티를 검색합니다.");
                        foreach ($this->pdata as $party => $pname) {
                            if ($this->getSearchAccess($this->pdata[$party]["파티"]) == "공개") {
                                $form->addButton("§l{$this->pdata[$party]["파티"]}\n§r§8파티장 : {$this->getPartyLeader($this->pdata[$party]["파티"])} 인원 : {$this->CountMember($this->pdata[$party]["파티"])}/4 가입제 : {$this->getJoinAccess($this->pdata[$party]["파티"])}");
                            }
                        }
                        $form->addButton("§l닫기");
                        $form->sendToPlayer($player);
                    }
                });
                $form->setTitle("Tele Party");
                $form->setContent("");
                $form->addButton("§l파티 생성\n§r§8파티를 생성합니다.");
                $form->addButton("§l파티 가입\n§r§8파티에 가입합니다.");
                $form->addButton("§l닫기");
                $form->sendToPlayer($player);
            } else {
            }
        }

        if (isset($this->mdata[$player->getName()])) {
            if ($this->mdata[$player->getName()]["직위"] == "파티장") {
                $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                    if (!is_numeric($data[0])) return;
                    if ($data[0] == 0) {
                        $form = $this->ui->CustomForm(function (Player $player, array $data) {
                            if (!isset($data[1])) {
                                $player->sendMessage("{$this->pre} 닉네임을 기입해주세요.");
                                return;
                            }
                            if (!$this->getServer()->getPlayer($data[1]) instanceof Player) {
                                $player->sendMessage("{$this->pre} 해당 유저는 접속해있지 않습니다.");
                                return;
                            }
                            if (isset($this->mdata[$this->getServer()->getPlayer($data[1])->getName()])) {
                                $player->sendMessage("{$this->pre} {$this->getServer()->getPlayer($data[1])->getName()}님은 이미 {$this->getParty($this->getServer()->getPlayer($data[1])->getName())} 파티에 소속되어있습니다.");
                                return;
                            }
                            $send = $this->InviteParty($this->getServer()->getPlayer($data[1])->getName(), $this->getParty($player->getName()));
                            if ($send !== true) {
                                $player->sendMessage("{$this->pre} 해당 유저가 다른 창을 열고있어 보낼 수 없습니다. 나중에 다시 시도하세요.");
                            } else {
                                $player->sendMessage("{$this->pre} 해당 유저에게 파티 초대장을 보냈습니다!");
                            }
                            return;
                        });
                        $form->setTitle("Tele Party");
                        $form->addLabel("파티 멤버를 초대합니다.");
                        $form->addInput("닉네임", "닉네임");
                        $form->sendToPlayer($player);
                    }
                    if ($data[0] == 1) {
                        $form = $this->ui->CustomForm(function (Player $player, array $data) {
                            if (!isset($data[1])) return false;
                            if (!isset($this->mdata[$data[1]])) {
                                $player->sendMessage("{$this->pre} 해당 파티의 멤버가 아닙니다.");
                                return;
                            }
                            if ($this->mdata[$data[1]]["직위"] == "파티장") {
                                $player->sendMessage("{$this->pre} 파티장은 내보낼 수 없습니다. ( 대소문자 구별 )");
                                return;
                            }
                            $this->KickMember($data[1]);
                            $player->sendMessage("{$this->pre} {$data[1]}님을 강퇴하였습니다!");
                            foreach ($this->pdata[$this->getParty($player->getName())]["파티원"] as $members) {
                                if ($this->getServer()->getPlayer($members) instanceof Player) {
                                    $this->getServer()->getPlayer($members)->sendMessage("{$this->pre} {$data[1]}님이 파티에서 강퇴되었습니다.");
                                }
                            }
                            if ($this->getServer()->getPlayer($data[1]) instanceof Player) {
                                $this->getServer()->getPlayer($data[1])->sendMessage("{$this->pre} {$this->getParty($player->getName())} 파티에서 강퇴되었습니다.");
                            }
                            return;
                        });
                        $form->setTitle("Tele Party");
                        $form->addLabel("파티 멤버를 강퇴합니다.\n멤버 목록 : {$this->getPartyMember($this->getParty($player->getName()))}");
                        $form->addInput("닉네임", "닉네임");
                        $form->sendToPlayer($player);
                    }
                    if ($data[0] == 2) {
                        $form = $this->ui->ModalForm(function (Player $player, array $data) {
                            if ($data[0] == true) {
                                foreach ($this->pdata[$this->getParty($player->getName())]["파티원"] as $members) {
                                    if ($this->getServer()->getPlayer($members) instanceof Player) {
                                        $this->getServer()->getPlayer($members)->sendMessage("{$this->pre} 파티가 해체되었습니다.");
                                    }
                                }
                                $this->removeParty($this->getParty($player->getName()));
                            }
                        });
                        $form->setTitle("Tele Party");
                        $form->setContent("\n정말로 {$this->getParty($player->getName())} 파티를 해체하시겠습니까?");
                        $form->setButton1("§l§8[예]");
                        $form->setButton2("§l§8[아니오]");
                        $form->sendToPlayer($player);
                    }
                });
                $form->setTitle("Tele Party");
                $form->setContent("");
                $form->addButton("§l파티 초대\n§r§8유저를 파티에 초대합니다.");
                $form->addButton("§l파티 멤버 강퇴\n§r§8파티원을 내보냅니다.");
                $form->addButton("§l파티 해체\n§r§8파티를 해체합니다.");
                $form->addButton("§l닫기");
                $form->sendToPlayer($player);
                return;
            }

            if ($this->mdata[$player->getName()]["직위"] !== "파티장") {
                $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                    if (!is_numeric($data[0])) return;
                    if ($data[0] == 0) {
                        if ($player instanceof Player) {
                            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                                if ($data[0] == true) {
                                    if ($this->mdata[$player->getName()]["직위"] == "파티장") {
                                        $player->sendMessage("{$this->pre} 파티장은 탈퇴할 수 없습니다!");
                                        $player->sendMessage("{$this->pre} 탈퇴하시려면 파티를 해체하여야합니다.");
                                        return;
                                    }
                                    $player->sendMessage("{$this->pre} {$this->getParty($player->getName())} 파티를 탈퇴하였습니다.");
                                    $this->ExitParty($player->getName());
                                    return;
                                }
                            });
                            $form->setTitle("Tele Party");
                            $form->setContent("\n정말로 {$this->getParty($player->getName())} 파티를 탈퇴하시겠습니까?");
                            $form->setButton1("§l§8[예]");
                            $form->setButton2("§l§8[아니오]");
                            $form->sendToPlayer($player);
                        }
                    }
                });
                $form->setTitle("Tele Party");
                $form->setContent("");
                $form->addButton("§l탈퇴하기\n§r§8소속된 파티를 탈퇴합니다.");
                $form->addButton("§l닫기");
                $form->sendToPlayer($player);
            }
        }
    }

    public function addParty($PartyName, $name, $search, $join) {
        if (isset($this->mdata[$name])) return;
        if (isset($this->pdata[$PartyName])) return;
        $this->pdata[$PartyName] = [];
        $this->pdata[$PartyName]["파티"] = $PartyName;
        $this->pdata[$PartyName]["파티장"] = $name;
        $this->pdata[$PartyName]["공개여부"] = $search;
        $this->pdata[$PartyName]["가입제도"] = $join;
        $this->pdata[$PartyName]["파티원"] = [];
        $this->pdata[$PartyName]["파티원"][$name] = $name;
        $this->mdata[$name] = [];
        $this->mdata[$name]["파티"] = $PartyName;
        $this->mdata[$name]["직위"] = "파티장";
        $this->mdata[$name]["파티채팅"] = "off";
    }

    public function getPartyLeader($PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        return $this->pdata[$PartyName]["파티장"];
    }

    public function getSearchAccess($PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        return $this->pdata[$PartyName]["공개여부"];
    }

    public function getJoinAccess($PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        return $this->pdata[$PartyName]["가입제도"];
    }

    public function AccessJoinParty($PartyName, $name) {
        if (!isset($this->pdata[$PartyName])) return;
        if (isset($this->mdata[$name])) return;
        if ($this->CountMember($PartyName) >= 4) return;
        if ($this->getServer()->getPlayer($name) instanceof Player) {
            $this->mode4[$PartyName] = "{$PartyName}:{$name}";
            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                if ($data[0] == true) {
                    $this->JoinParty(explode(":", $this->mode4[$this->getParty($player->getName())])[0], explode(":", $this->mode4[$this->getParty($player->getName())])[1]);
                    if ($this->getServer()->getPlayer(explode(":", $this->mode4[$this->getParty($player->getName())])[1]) instanceof Player) {
                        $this->getServer()->getPlayer(explode(":", $this->mode4[$this->getParty($player->getName())])[1])->sendMessage("{$this->pre} 가입요청이 수락되었습니다!");
                    }
                    unset($this->mode4[explode(":", $this->mode4[$this->getParty($player->getName())])[1]]);
                    return;
                } else {
                    $player->sendMessage("{$this->pre} 가입요청을 거절하였습니다.");
                    if ($this->getServer()->getPlayer(explode(":", $this->mode4[$this->getParty($player->getName())])[1]) instanceof Player) {
                        $this->getServer()->getPlayer(explode(":", $this->mode4[$this->getParty($player->getName())])[1])->sendMessage("{$this->pre} 가입요청이 거절되었습니다.");
                    }
                    unset($this->mode4[explode(":", $this->mode4[$this->getParty($player->getName())])[1]]);
                    return;
                }
            });
            $form->setTitle("Tele Party");
            $form->setContent("\n{$name}님이 파티 가입요청을 보냈습니다!\n수락하시겠습니까?");
            $form->setButton1("§l§7[수락]");
            $form->setButton2("§l§7[거절]");
            $form->sendToPlayer($this->getServer()->getPlayer($this->getPartyLeader($PartyName)));
        }
    }

    public function JoinParty($PartyName, $name) {
        if (!isset($this->pdata[$PartyName])) return;
        if (isset($this->mdata[$name])) return;
        if ($this->CountMember($PartyName) >= 4) return;
        $this->pdata[$PartyName]["파티원"][$name] = $name;
        $this->mdata[$name] = [];
        $this->mdata[$name]["파티"] = $PartyName;
        $this->mdata[$name]["직위"] = "파티원";
        $this->mdata[$name]["파티채팅"] = "off";
        foreach ($this->pdata[$PartyName]["파티원"] as $members) {
            if ($this->getServer()->getPlayer($members) instanceof Player) {
                $this->getServer()->getPlayer($members)->sendMessage("{$this->pre} {$name}님이 파티에 새로 가입하셨습니다!");
            }
        }
    }

    public function getParty($name) {
        if (!isset($this->mdata[$name])) return "없음";
        return $this->mdata[$name]["파티"];
    }

    public function InviteParty($name, $PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        if (isset($this->mdata[$name])) return;
        if ($this->CountMember($PartyName) >= 4) return;
        if ($this->getServer()->getPlayer($name) instanceof Player) {
            $this->mode[$this->getServer()->getPlayer($name)->getName()] = $PartyName;
            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                if ($data[0] == true) {
                    $player->sendMessage("{$this->pre} {$this->mode[$player->getName()]} 파티의 초대를 수락하여 가입되었습니다!");
                    $this->JoinParty($this->mode[$player->getName()], $player->getName());
                    unset($this->mode[$player->getName()]);
                    return;
                } else {
                    $player->sendMessage("{$this->pre} {$this->mode[$player->getName()]} 파티의 초대를 거절하였습니다.");
                    if ($this->getServer()->getPlayer($this->getPartyLeader($this->mode[$player->getName()]))) {
                        $this->getServer()->getPlayer($this->getPartyLeader($this->mode[$player->getName()]))->sendMessage("{$this->pre} {$player->getName()}님이 초대를 거절하셨습니다.");
                    }
                    unset($this->mode[$player->getName()]);
                    return;
                }
            });
            $form->setTitle("Tele Party");
            $form->setContent("\n{$PartyName} 파티에서 초대장이 왔습니다! ( 파티장 : {$this->getPartyLeader($PartyName)})\n수락하시겠습니까?");
            $form->setButton1("§l§7[수락]");
            $form->setButton2("§l§7[거절]");
            $send = $form->sendToPlayer($this->getServer()->getPlayer($name));
            return $send;
        }
    }

    public function KickMember($name) {
        if (!isset($this->mdata[$name])) return;
        $this->setChatMode($name, "off");
        unset($this->pdata[$this->getParty($name)]["파티원"][$name]);
        unset($this->mdata[$name]);
        return;
    }

    public function setChatMode($name, $type) {
        if (!isset($this->mdata[$name])) return;
        $this->mdata[$name]["파티채팅"] = $type;
    }

    public function getPartyMember($PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        $member = "| ";
        foreach ($this->pdata[$PartyName]["파티원"] as $members) {
            $member .= "{$members} | ";
        }
        return $member;
    }

    public function removeParty($PartyName) {
        if (!isset($this->pdata[$PartyName])) return;
        foreach ($this->pdata[$PartyName]["파티원"] as $members) {
            $this->setChatMode($members, "off");
            unset($this->mdata[$members]);
        }
        unset($this->pdata[$PartyName]);
    }

    public function ExitParty($name) {
        if (!isset($this->mdata[$name])) return;
        if ($this->mdata[$name]["직위"] == "파티장") return;
        $this->setChatMode($name, "off");
        unset($this->pdata[$this->getParty($name)]["파티원"][$name]);
        unset($this->mdata[$name]);
        return;
    }

}
