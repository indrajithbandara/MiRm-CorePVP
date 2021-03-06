<?php
namespace mirm_core;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class mirm_core extends PluginBase implements Listener
{
    public function onEnable()
    {
        Server::getInstance()->getPluginManager()->registerEvents($this,$this);

        if(!file_exists($this->getDataFolder())){
            mkdir($this->getDataFolder(), 0744, true);
        }
        
        global $config;
        $config = new Config($this->getDataFolder() . "configcore.yml", Config::YAML,
                array(
                    "HP"=>50,
                    "コア破壊得点"=>1,
                    "キル得点"=> 1,
                    "ゲームモード"=> "off",
                    "a座標のX"=>100,"a座標のY"=>100,"a座標のZ"=>100,
                    "b座標のX"=>100,"b座標のY"=>100,"b座標のZ"=>100)
        );
        global $config2;
        $config2 = new Config($this->getDataFolder() . "configlevel.yml", Config::YAML,
            array('プレイヤー名_kill',"10")
        );


        if(Server::getInstance()->getPluginManager()->getPlugin("EconomyAPI") !=null) {
            $this->EconomyAPI = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        }else{
            Server::getInstance()->getLogger()->info("EconomyAPIが読み込めませんでした");
        }
        ///
        ///
        ///

        global $gm;
        $gm = $config->get("ゲームモード");


        $this->team = [1 => [] , 2 => [] ];
        $this->joinedpvp = array();
        $this->teamcore =array();

        //teamcore代入
        $this->teamcore[1]=$config->get("HP");
        $this->teamcore[2]=$config->get("HP");
        ////
        Server::getInstance()->getLogger()->info("mirm-coreが読み込まれました");
    }

    public function onDisable()
    {
        unset($this->players);
        unset($this->joined);
        unset($this->team);
        unset($this->teamcore);
        foreach ($this->getServer()->getOnlinePlayers() as $p) {
            $p->kick("鯖がリロードまたは終了しました。");
        }
    }


    /*コアの方は
座標、HP、コアにするブロックのID、チーム分け&TPのブロックIDは、configから帰れるようにしてもらって。
コアを壊されたら写真にあるところらへんに、§e壊した人の名前§fがチーム名のコアを破壊しています！
残りHPというふうに表示、そして、コアの体力がなくなったら全員リスポに戻されてワープできなくなって３０秒後に再起動という形にして欲しいです！
チーム分けは、出撃する時にブロックタップして、チーム分けされてそしてそのチームの決めた座標にTPという形にして欲しいです

そして、チーム分けのブロックをタップしたら

君は○○チームだ健闘を祈る！

という表示をして欲しいです


   */
    public function oninteract(PlayerInteractEvent $event){
        global $gm;
        if($gm != "core") return;

        global $config;

        $blockid = $event->getBlock()->getId();
        $player = $event->getPlayer();
        $name = $player->getName();
        echo $blockid;
        if(isset($this->end) && $this->end){
            return;
        }

        global $gm;
        echo $gm;
        if($gm != "core") return;

        if ($blockid == 19){
            unset($blockid );
            echo 1;
            if(isset($this->joinedpvp[$name] )){
                echo 2;
                if($this->joinedpvp[$name] == 1 ){
                    $player->sendMessage(TextFormat::RED ."[PVP]あなたはすでにpvpに参加しています。");
                    return;
                }
            }
            $this->joinedpvp[$name] =1;
            if(count($this->team[1]) <= count($this->team[2])){
                $this->team[1][$name] = 1;
                unset( $this->team[2][$name]);
                $teamname = "TeamA";
                $pos = new  Vector3($config->get("a座標のX"),$config->get("a座標のY"),$config->get("a座標のZ"));
            }else{
                $this->team[2][$name] = 1;
                unset( $this->team[1][$name]);
                $teamname = "TeamB";
                $c =$config->get("これは設定です");
                $pos = new  Vector3($config->get("b座標のX"),$config->get("b座標のY"),$config->get("b座標のZ"));
            }

            $player->sendMessage("君は".$teamname."チームだ健闘を祈る！");
            $player->teleport($pos);
            $this->setTitle($player);
        }
    }

    public function onbreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        global $config;

        if($event->getBlock()->getID() == 247 ||$event->getBlock()->getID() == 247 ){
            $event->setCancelled(true);
            if($event->getBlock()->getID() ==247 and $event->getBlock()->getDamage() == 1 and isset($this->team[2][$name])){
                $teamname="TeamA";
                $this->teamcore[1]=$this->teamcore[1]-1;
                if($this->teamcore[1] ==0){
                    $teamname ="TeamB";
                    $ok=1;
                }
            }
            elseif($event->getBlock()->getID() == 247  and $event->getBlock()->getDamage() == 2 and isset($this->team[1][$name])){
                $teamname="TeamB";
                $this->teamcore[2]=$this->teamcore[2]-1;
                if($this->teamcore[2] ==0){
                    $ok=1;
                    $teamname ="TeamA";
                }
            }else{
                $ng =1;
                $players = Server::getInstance()->getOnlinePlayers();
                foreach ($players as $player) {
                    $player->sendPopUp(TextFormat::YELLOW.$name."が自チームのコアを攻撃。");
                }
            }
            if(!isset($ng)){
                $players = Server::getInstance()->getOnlinePlayers();
                $money= $config->get("コア破壊得点");
                $this->EconomyAPI->addMoney($name,$money);
                foreach ($players as $playerass) {
                    //壊した人の名前§fがチーム名
                    $playerass->sendPopUp("§e".$name."が".$teamname."のコアを破壊しています！ \n残りHP TeamA:".$this->teamcore[1]." TeamB:".$this->teamcore[2]);
                }
                if(isset($ok)){
                    unset($ok);
                    $this->getServer()->broadcastMessage(TextFormat::RED ."[PVP]".$teamname."チームが勝ちました。");

                    ///プレイヤーtpと動けなくする
                    foreach ($players as $playerass) {
                        $playerass->teleport(Server::getInstance()->getDefaultLevel()->getSpawnLocation());
                    }

                    unset($this->joined);
                    unset($this->joinedpvp);
                    unset($this->team);
                    unset($this->teamcore);

                    ///
                    $this->team = [1 => [] , 2 => [] ];
                    $this->joinedpvp = array();
                    $this->teamcore =array();

                    //teamcore代入
                    $this->teamcore[1]=$config->get("HP");
                    $this->teamcore[2]=$config->get("HP");
                    ////

                }
            }
        }
    }


    public function onCommand(CommandSender $sender, Command $command,$label,array $args)
    {

        /** @var Player $player */
        $player = $sender;

        $name = $player->getName();

        global /** @var Config $config */
        $config;

        switch ($command->getName()) {

            case "corepvp": {

                if(!isset($args[0])){
                    $sender->sendMessage("Usege: /corepvp <mode/sethp/setspawn/killpoint/corepoint/kills>");
                    break;
                }

                switch ($args[0]){
                    case "mode":{
                        if(!isset($args[1])){
                            $sender->sendMessage("Usege: /corepvp mode <off:ffa:core>");
                            return;
                        }

                        switch ($args[1]){
                            case "off":
                            case "core":
                            case "ffa":
                                break;
                            default:
                                $sender->sendMessage("Usege: /corepvp mode <off:ffa:core>");
                                return;
                        }

                        $mode=$args[1];
                        $config->set("ゲームモード",$mode);
                        $config->save();

                        $sender->sendMessage("ゲームモード変更完了。再起動してください。");

                        break;
                    }
                    case "sethp":{
                        if(!isset($args[1])&&is_numeric($args[1])){
                            $sender->sendMessage("Usege: /corepvp sethp 数字");
                            return;
                        }

                        $mode=$args[1];
                        $config->set("HP",$mode);
                        $config->save();

                        $sender->sendMessage("HP変更完了。再起動してください。");

                        break;
                    }
                    case "setspawn":{
                        if(!isset($args[1])&&is_numeric($args[1])){
                            $sender->sendMessage("Usege: /corepvp setspawn <team1:team2>");
                            return;
                        }

                        switch ($args[1]){
                            case "team1":
                                $config->set("a座標のX",$player->getFloorX());
                                $config->set("a座標のY",$player->getFloorY());
                                $config->set("a座標のZ",$player->getFloorZ());
                                break;
                            case "team2":
                                $config->set("b座標のX",$player->getFloorX());
                                $config->set("b座標のY",$player->getFloorY());
                                $config->set("b座標のZ",$player->getFloorZ());
                                break;
                            default:
                                $sender->sendMessage("Usege: /corepvp setspawn <team1:team2>");
                                return;
                        }


                        $config->save();

                        $sender->sendMessage("チームスポーン変更完了。再起動してください。");


                        break;
                    }
                    case "killpoint":{
                        if(!isset($args[1])&&is_numeric($args[1])){
                            $sender->sendMessage("Usege: /corepvp killpoint 数字");
                            return;
                        }

                        $mode=$args[1];
                        $config->set("キル得点",$mode);
                        $config->save();

                        $sender->sendMessage("キル得点変更完了。再起動してください。");


                        break;
                    }
                    case "corepoint":{
                        if(!isset($args[1])&&is_numeric($args[1])){
                            $sender->sendMessage("Usege: /corepvp corepoint 数字");
                            return;
                        }

                        $mode=$args[1];
                        $config->set("コア破壊得点",$mode);
                        $config->save();

                        $sender->sendMessage("コア破壊得点変更完了。再起動してください。");

                        break;
                    }
                    case "kills":
                        global /** @var Config $config2 */
                        $config2;
                        if(!isset($args[1])){
                            $kill = $config2->get($name."_kill");
                            $sender->sendMessage("「CorePVP」".$name."のキル数は".$kill."です");
                        }
                        $name=$args[1];
                        if($config2->exists($name."_kill")){
                            $kill = $config2->get($name."_kill");
                            $sender->sendMessage("「CorePVP」".$name."のキル数は".$kill."です");
                        }else{
                            $sender->sendMessage("「CorePVP」".$name."なる人はいないです");
                        }

                }
            }
            break;
            case "tc": {
                global $gm;
                if($gm != "core") return;

                $name = $sender->getName();
                $players = Server::getInstance()->getOnlinePlayers();
                if(isset($this->team[1][$name])){
                    foreach ($players as $player) {
                        if(isset($this->team[1][$player->getName()])){
                            $player->sendMessage(TextFormat::BLUE."[チームチャット]".$name.":".implode(" ", $args));
                        }}
                }elseif(isset($this->team[2][$name])){
                    foreach ($players as $player) {
                        if(isset($this->team[2][$player->getName()])){
                            $player->sendMessage(TextFormat::BLUE."[チームチャット]".$name.":".implode(" ", $args));
                        }}
                }
                break;
            }
        }
    }
    public function onPlayerDeath(PlayerDeathEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        unset($this->joinedpvp[$name]);
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        unset($this->joinedpvp[$name]);
    }

    public function getTeam($name)
    {
        if (isset($this->team[1][$name])) {
            return "a";
        } elseif (isset($this->team[2][$name])) {
            return "b";
        } else {
            return "f";
        }

    }


    ////共食い
    public function optionbow(EntityDamageEvent $event)
    {
        global $gm;
        if($gm != "core") return;

        if ($event instanceof EntityDamageEvent) {
            if ($event instanceof EntityDamageByEntityEvent) {
                if ($event->getDamager() instanceof Player && $event->getEntity() instanceof Player) {
                    $sender = $event->getDamager();
                    $reciever = $event->getEntity();
                    unset($sname);
                    unset($rname);
                    $sname = $sender->getName();
                    $rname = $reciever->getName();
                    if (!isset($this->joinedpvp[$sname])) {
                        $sender->sendPopUp(TextFormat::YELLOW . "PVPに参加していないプレイヤーは攻撃できません。");
                        $event->setCancelled();
                    }
                    if (isset($this->team[1][$sname]) and isset($this->team[1][$rname])) {
                        $sender->sendPopUp(TextFormat::YELLOW . "同チームのプレイヤーは攻撃できません。");
                        $event->setCancelled();
                    } elseif (isset($this->team[2][$sname]) and isset($this->team[2][$rname])) {
                        $sender->sendPopUp(TextFormat::YELLOW . "同チームのプレイヤーは攻撃できません。");
                        $event->setCancelled();
                    }
                }
            }
        }
    }


    /**
     * @param PlayerJoinEvent $event
     */
    public function Onjoin(PlayerJoinEvent $event){
        global /** @var Config $config2 */
        $config2;
        $player = $event->getPlayer();
        if(!$config2->exists($player->getName())){
            $config2->set($player->getName()."_kill",0);
            $config2->set($player->getName()."_level",0);
        }
        $this->setTitle($player);
    }

    public function death(PlayerDeathEvent $event){

        global $gm;
        if($gm == "off") return;

        $player = $event->getPlayer();
        global $config;
        global $config2;
        $ev = $player->getLastDamageCause();
        if ($ev instanceof EntityDamageByEntityEvent) {
            //$event->setDeathMessage();
            $killer = $ev->getDamager();
            $killername = $killer->getName();
            if ($killer instanceof Player) {
                $lv = $config2->get($killername."_level");
                $kill = $config2->get($killername."_kill");
                $kill++;
                if($lv !=0&$lv%100==0){
                    if($config2->get($killername)!=15) {
                        $lv= round($kill/ 100);
                    }
                }
                /* Levelプラグインで倒した時に
                 君は○○を倒した！
                 ○○PMをゲットした！
                 次のレベルまで残り○○キル*/

                $money = $config->get("キル得点");
                $this->EconomyAPI->addMoney($killername,$money);
                $this->setTitle($killer);
            }
        }
    }

    public function setTitle(Player $player){
        $name =$player->getName();
        global $config2;
        $lv = $config2->get($name."_lv")!=15?$config2->get($name."_lv"):"§dMax";

        ///あとで
        $team =null;
        if(isset($this->team[1][$name])){
            $team ="<TeamA>";
        }elseif(isset($this->team[2][$name])) {
            $team ="<TeamB>";
        }
        $player->setDisplayName("<".$name.$team.">");
        $player->setNameTag("<".$name.$team.">");
    }
}