# bitpvp/replay

```php
use bitpvp\replay\ReplayAPI;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{
    protected function onEnable() : void{
        ReplayAPI::register($this);
    }

    protected function onDisable() : void{
        ReplayAPI::shutdown();
    }
}
```

## Recording

```php
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\recorder\RecordSettings;
use pocketmine\world\World;

$settings = new RecordSettings(
    plugin: $this,
    savePath: $this->getDataFolder() . "replays",
    recordWorldSnapshot: true, // true if you want to record the world
    recordInterval: 1 // ticks
);

$replayId = ReplayAPI::startRecording(
    $settings,
    $world,
    // onSave closure
    function(string $id, bool $success, string $pathOrError) : void{
        // your code here
    }
);

ReplayAPI::stopRecording($replayId);
```


## Replaying

```php
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\data\ReplayInformation;
use bitpvp\replay\replayer\ReplaySettings;
use bitpvp\replay\replayer\ReplayPlayer;

$information = new ReplayInformation(
    identifier: $replayId,
    timestamp: new \DateTime(),
    duration: 20 * 60
);

$settings = new ReplaySettings(
    plugin: $this,
    speed: 1.0,
    loop: false
);

// The .replay files are different from the ones you get
// from the storage, the difference is that the .replay stores
// in compressed format the whole mechanics/actions/logs etc
// the way that this files are formatted can be seen in the
// replay/ReplayProtocol.php file 

ReplayAPI::startReplay(
    $settings,
    $viewer,
    $information,
    $this->getDataFolder() . "replays/{$replayId}.replay", 
    function(bool $success, ?ReplayPlayer $replay, string $error) : void{
        if(!$success || $replay === null){
            $this->getLogger()->error("No se pudo cargar la replay: " . $error);
            return;
        }
        $replay->unpause();
    }
);
```

## Storage

```php
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\storage\type\JsonReplayStorage;

/** @var \pocketmine\player\Player $viewer */
$storage = new JsonReplayStorage($this->getDataFolder() . "replays");
ReplayAPI::setStorage($storage);
ReplayAPI::startReplayFromStorage(
    new ReplaySettings($this, 1.0, false),
    $viewer,
    $replayId,
    onReady: function(bool $success, ?ReplayPlayer $replay, string $error) : void{
        if($success){
            // ReplayPlayer is never null when success
            $replay->unpause();
        }
    }
);
```



## Control

```php
use bitpvp\replay\replayer\util\ReplayPlayDirection;

$replay->pause();
$replay->unpause();
$replay->togglePause();
$paused = $replay->isPaused();

$replay->setSpeed(2.0); // speed > 0
$replay->setPlayDirection(ReplayPlayDirection::BACKWARDS);
$replay->skip(ReplayPlayDirection::FORWARDS, 100);
$replay->skipToTick(200);

$currentTick = $replay->getCurrentTick();
$duration = $replay->getData()->getInformation()->getDuration();
$eventLogs = $replay->getData()->getEventLogsByTick($currentTick);

// Inventory!
$playerEntityId = 123; // ID 
$inventory = $replay->getEntityInventory($playerEntityId);
if($inventory !== null){
    $items = $inventory["contents"];
    $armor = $inventory["armor"];
    $offhand = $inventory["offhand"]; // nullable
    $heldSlot = $inventory["heldSlot"]; 
}
```

## Events


- `ReplayStartEvent`
- `ReplayEndEvent`
- `ReplayTogglePauseEvent`
- `ReplayRestartEvent`
- `ReplayChangeDirectionEvent`

