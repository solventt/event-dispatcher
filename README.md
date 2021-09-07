### Table of Contents
1. [Installing](#Installing)
2. [Container Listener Provider](#container-listener-provider)
3. [Classic Listener Provider](#classic-listener-provider)
4. [Reflection Listener Provider](#reflection-listener-provider)
5. [Deferred events](#deferred-events)
6. [Listeners constraints according to PSR-14](#listeners-constraints-according-to-psr-14)
7. [Stopped Propagation Events](#stopped-propagation-events)
8. [Iterators instead of arrays](#iterators-instead-of-arrays)
9. [Creating listener providers](#creating-listener-providers)

It's the PSR-14 compatible Event Dispatcher which provides several ways of determining "Event-Listeners" definitions.

>NOTE: All the examples below, which include the DI container, are written using the [php-di](https://github.com/PHP-DI/PHP-DI) library. But you can utilize any DI container in your code.

### Installing
```
// php 7.4+
composer require solventt/event-dispatcher ^0.1

// php 8.0+
composer require solventt/event-dispatcher ^1.0
```
**Note:** if you are going to use Container Listener Provider you must install any PSR-11 compatible DI Container.

### Container Listener Provider
This provider supports only ***string*** definitions of listeners. And listeners must implement the ```__invoke``` method. It allows "lazy" creation of callable listeners while dispatching an event.

Remember:
- the executing order of listeners is as you specified in the DI container definition;
- this provider doesn't support dynamic binding/unbinding of listeners to/from events using the ```ON/OFF``` or ```ADD/REMOVE``` methods

The "Event-Listeners" definition for the DI container,

The first format:
```php
return [
    'eventsToListeners' => [
        
        FirstEvent::class => [
                InvokableListenerOne::class,
                InvokableListenerTwo::class,
        ],
        
        SecondEvent::class => [
                InvokableListenerThree::class,
                InvokableListenerFour::class,
        ],   
    ]
];
```
The second format without specifying events (listeners events will be automatically resolved):
```php
return [
    'eventsToListeners' => [
        
        InvokableListenerOne::class,
        InvokableListenerTwo::class,
        InvokableListenerThree::class,
        InvokableListenerFour::class, 
    ]
];
```
The third mixed format (arrays and strings):
```php
return [
    'eventsToListeners' => [
        
        FirstEvent::class => [
            InvokableListenerOne::class,
            InvokableListenerTwo::class,
        ],
        InvokableListenerThree::class,
        InvokableListenerFour::class,

    ]
];
```
The Event Dispatcher definition for the DI container:
```php
return [
    EventDispatcherInterface::class => function (ContainerInterface $container) {
        
        $provider = new ContainerListenerProvider($container);
        
        return new EventDispatcher($provider);       
    }
];
```
Somewhere in the code:
```php
public function __construct(private EventDispatcherInterface $dispatcher) {}

...

$this->dispatcher->dispatch(new FirstEvent());
```
```'eventsToListeners``` - the definition name which by default is recognized by the Container Provider.
If you want to use a different name you have to pass it to the ```ContainerListenerProvider``` constructor as a second argument:
```php
return [
    'yourCustomName' => [
        
        FirstEvent::class => [
                InvokableListenerOne::class
                InvokableListenerTwo::class
        ],  
    ],

    EventDispatcherInterface::class => function (ContainerInterface $container) {
        
        $provider = new ContainerListenerProvider($container, 'yourCustomName');
        
        return new EventDispatcher($provider);       
    }
];
```

### Classic Listener Provider
This provider supports only callable definitions of listeners.
You can assign listeners to events using:
- a provider constructor
- and/or the ```ON``` method (the ```OFF``` method unbinds a listener)

**Using:**
```php
$provider = new ClassicListenerProvider([
    FirstEvent::class => [
        new InvokableClass(),
        [ArrayCallable::class, 'test'],
        [new ArrayCallable(), 'test2']
    ],    
    
    SecondEvent::class => [    
        'usualFunc',
         $someClosure                
    ]
]);

$dispatcher = new EventDispatcher($provider);

$dispatcher->dispatch(new FirstEvent());
```
If you use the DI Container your definition might look like:
```php
return [
    EventDispatcherInterface::class => function (ContainerInterface $container) {
    
        $someClosure = function (FirstEvent $event): void {};
        
        $provider = new ClassicListenerProvider([
            SomeEvent::class => [
                new InvokableClass(),
                [ArrayCallable::class, 'test'],
                [new ArrayCallable(), 'test2'],
                'usualFunc',
                $someClosure                
            ],
        ]);
        
        return new EventDispatcher($provider);       
    }
];
```
**Also, you can specify the listener execution order** (an integer parameter). The higher the value, the earlier it will be called:
```php
...
[
    SomeEvent::class => [
        [new InvokableClass(), 3],
        [[ArrayCallable::class, 'test'], 6],
        [[new ArrayCallable(), 'test2'], 10],
        ['usualFunc', 1],
        [$someClosure, 12]                
    ],
]
...
```
The "Event-Listeners" definition using the ```ON``` method somewhere in the code:
```php
public function __construct(private EventDispatcherInterface $dispatcher) {}

...

$this->dispatcher->on(FirstEvent::class, new InvokableClass());

$this->dispatcher->on(SecondEvent::class, [ArrayCallable::class, 'test']);

$this->dispatcher->on(ThirdEvent::class, function (ThirdEvent $event): void {});
```
And with the priority:
```php
$this->dispatcher->on(FirstEvent::class, new InvokableClass(), 4);
```
To unbind a listener from an event use the ```OFF``` method:
```php
$this->dispatcher->off(FirstEvent::class, new InvokableClass());

$this->dispatcher->off(SecondEvent::class, [ArrayCallable::class, 'test']);
```

### Reflection Listener Provider
This provider admits only callable definitions of listeners but **without specifying events** (it will be automatically resolved).

You can assign listeners to events using:
- the provider constructor
- and/or the ```ADD``` method (the ```REMOVE``` method unbinds a listener)

**Using:**
```php
$provider = new ReflectionListenerProvider([
    new InvokableClass(),
    [ArrayCallable::class, 'test'],
    [new ArrayCallable(), 'test2'],
    'usualFunc',
    $someClosure                          
]);

$dispatcher = new EventDispatcher($provider);

$dispatcher->dispatch(new FirstEvent());
```
With the listener execution order:
```php
...
[
    [new InvokableClass(), 0],
    [[ArrayCallable::class, 'test'], 2],
    [new ArrayCallable(), 'test2'],
    ['usualFunc', 3],  // will be executed the first
    $someClosure       // 0 - a default priority, if it isn't specified explicitly                   
]
...
```
The "Event-Listeners" definition using the ```ADD``` method somewhere in the code:
```php
public function __construct(private EventDispatcherInterface $dispatcher) {}

...

$this->dispatcher->add(new InvokableClass());

$this->dispatcher->add([ArrayCallable::class, 'test']);

$this->dispatcher->add(function (ThirdEvent $event): void {});
```
And with the priority:
```php
$this->dispatcher->add(new InvokableClass(), 4);
```
To unbind a listener from an event use the ```REMOVE``` method:
```php
$this->dispatcher->remove(new InvokableClass());

$this->dispatcher->remove([ArrayCallable::class, 'test']);
```

### Deferred events
In some cases you may need to use deferred events.
```php
public function __construct(private EventDispatcherInterface $dispatcher) {}

...

$this->dispatcher->defer(new FirstEvent());  // no listeners are called;

$this->dispatcher->defer(new SecondEvent()); // no listeners are called;

$this->dispatcher->dispatchDeferredEvents(); // FirstEvent and SecondEvent are dispatched
```

### Listeners constraints according to PSR-14
Quotes from the PSR-14: 
1) "A Listener MUST have one and only one parameter, which is the Event to which it responds";
2) "A Listener SHOULD have a void return, and SHOULD type hint that return explicitly".

You can say that SHOULD not equal MUST. But I can't imagine what use-cases might require returning values from listeners and then use them. Anyway this dispatcher does not implement it.

So in this package you can't specify:
- an empty listener signature;
- more than one parameter in a listener signature;
- any return type besides ```void```. Omitted return type is also forbidden

You must provide a type hint of the listener argument - it can be an existent class or the ```object``` type. Other type-hints are not accepted.

**In these cases an exception will be thrown:**
```php
// Listeners callbacks

public function noParameters(): void {}

public function moreThanOneParameter(FirstEvent $event, string $name): void {}

public function undefinedParameterType($event): void {}

public function noReturnType(object $event) {}

public function wrongReturnType(FirstEvent $event): string {}
```
But you can **switch off** these listener constraints. It is necessary to pass ```false``` as the argument in the constructor of some listener provider:
```php
$provider = new ContainerListenerProvider($container, 'someDefinitionName', false);
...
$provider = new ClassicListenerProvider([...definition...], false);
```
However, the listeners provided without corresponding events ignores this setting because it requires a type-hinted argument for resolving an event:
```php
return [
    'eventsToListeners' => [
        
        FirstEvent::class => [InvokableListenerOne::class], // supports switching off the listener constraints
        InvokableListenerTwo::class, // doesn't support switching off the listener constraints
    ]
];
```
Also, ```ReflectionListenerProvider``` does not support switching off the listener constraints.

### Stopped Propagation Events
Your events can implement ```StoppableEventInterface``` (from PSR-14) to have more control over the listeners execution.
For example, we have the following event class:
```php
class FirstEvent implements StoppableEventInterface
{
    public string $result = '';

    public function isPropagationStopped(): bool
    {
        return (bool) preg_match('/stop/', $this->result);
    }
}
```
And there are three listeners:
```php
public function __invoke(FirstEvent $event): void
{
    $event->result = 'First';
}
...
public function __invoke(FirstEvent $event): void
{
    $event->result .= '-stop';
}
...
public function __invoke(FirstEvent $event): void
{
    $event->result .= '-Test';
}
```
The listeners definition:
```php
return [
    'eventsToListeners' => [
           
        FirstEvent::class => [
                InvokableListenerOne::class,
                InvokableListenerTwo::class,   // adds the '-stop' string in the $result property of FirstEvent
                InvokableListenerThree::class, // will not be executed
        ],  
    ],
    
    EventDispatcherInterface::class => function (ContainerInterface $container) {
        
        $provider = new ContainerListenerProvider($container);
        
        return new EventDispatcher($provider);    
    }
];
```
The third listener will not be executed because the ```isPropagationStopped()``` method of the ```FirstEvent``` returns ```true``` after the second listener is being executed.

### Iterators instead of arrays
Providers also receive iterators in their constructor. But an iterator must implement the ```ArrayAccess``` interface.

Example 1:
```php
$definition = [
    FirstEvent::class => [
        new InvokableClass(),
        [ArrayCallable::class, 'test'],
    ]
];

$provider = new ClassicListenerProvider(new ArrayIterator($definition));
```
Example 2:
```php
class ListenerIterator extends ArrayIterator
{
    public function __construct()
    {
        parent::__construct($this->getListeners());
    }

    private function getListeners(): array
    {
        return [
            FirstEvent::class => [
                new InvokableClass(),
                [[ArrayCallable::class, 'test'], 2],
            ],

            SecondEvent::class => [
                'usualFunc',
                [new ArrayCallable(), 'test2']
            ]
        ];
    }
}
```
Somewhere in the code:
```php
$provider = new ClassicListenerProvider(new ListenerIterator());
```
### Creating listener providers
You need to implement the PSR-14 ```ListenerProviderInterface```. And if you want your provider to support the ```ON/OFF``` methods you also have to implement ```SubscribingInterface```.
