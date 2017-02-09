Jasny's MVC basics for PHP
==========================

Includes a router, a controller base class and a class for loading views.

### What is MVC?

The Model View Controller pattern splits the user interface interaction into three distinct roles. Each with it's own
responsibility.

> View: "Hey, controller, the user just told me he wants item 4 deleted."
> Controller: "Hmm, having checked his credentials, he is allowed to do that... Hey, model, I want you to get item 4 and
> do whatever you do to delete it."
> Model: "Item 4... got it. It's deleted. Back to you, Controller."
> Controller: "Here, I'll collect the new set of data. Back to you, view."
> View: "Cool, I'll show the new set to the user now."

- [Andres Jaan Tack @ stackoverflow](http://stackoverflow.com/a/1015853/1160754)
