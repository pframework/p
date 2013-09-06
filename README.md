README
======

Short Intro
-----------

Tagline: "Because PHP *IS* my framework"

P is a PHP framework of very limited scope.  It contains more
architecture than lines of code.  This is on purpose, this likely
won't change.

New features need to be extremely vetted for inclusion in this
framework.  Why?  Because here, NIH means "not in here".  Whatever
feature you think would be nice to have probably already has a better
place where it could live.

Long Intro / General Perspectives
---------------------------------

Out of the box, P delivers just enough features to allow PHP
developers to build structurally sound applications without a lot
of boilerplate code.  P's learning curve is shallow enough so that
developers can be immediately productive with just a well rounded
understanding of PHP itself.

Things you'll like or won't miss in P:

* You'll like the simple *dependency injection*
* You won't miss the lack of HTTP Request/Response abstraction
* You'll like that most objects are built on well known SPL structures
* You won't miss hard to debut recusive and endless stack traces
* You'll like the blissfully short implementations
* (In other words) You won't miss the endless abstractions

That said P promotes an appliation architecture with just a
handful of concepts and features:

* Fast name based service/dependency injection
* Simple built-in router for both HTTP and CLI request handling
* Configuration file management and processing
* Basic PHP/HTML, CLI, and REST output handling
* Application lifecycle callback registration

Installation
------------

1) Use the skeleton project:

    composer create-project p/p-micro-skeleton ./project

2) Create a composer.json and install:

    "require": {
        "pframework/p": "dev-master"
    }


Hello World
-----------

The painfully simple and useless hello world:

    <?php
    require __DIR__ '/../vendor/autoload.php';
    (new P\Application())->addRoute(['GET /', function () { echo 'Hello World'; }])->run();


Base Services
-------------

Todo.

Base Lifecycle Scopes
---------------------

Todo.

