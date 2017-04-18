# FCA-Tools-Bundle

This website was built in order to provide users with a diverse set of tools used in Formal Concept Analysis.
It is available at [FCA Tools Bundle](https://fca-tools-bundle.com)

## Features
* Create dyadic contexts
* Import dyadic contexts
* Generate a dyadic context's concepts
* Find a concept in a dyadic context without generating all its concepts
* Generate and navigate a dyadic context's concept concepts
* Create triadic contexts
* Import triadic contexts
* Generate a triadic context's concepts
* Find a concept in a triadic context without generating all its concepts
* Navigate a triadic context by using perspectives
* Navigate on a large triadic context by using perspectives for which the full list of concepts cannot be computed
* View and interact with all the public contexts uploaded by other users
* Make your own context public to other users

## Requirements

### For the website
* A web development environment
* PHP 5.5.9 or higher
* MongoDB
* PHP MongoDB extension. Note: Use the legacy extension for now because Doctrine requires it.
* The [usual Symfony application requirements](http://symfony.com/doc/current/reference/requirements.html).
* Composer
* Memcached

### For the scripts
* ASP progarmming language

### Deprecated
* Python - A python script used to generate the concept lattice but now it has been replaced with PHP.
  The script is still there and can be used for reference or for debugging.

## Installation

An in-dept windows installation guide can be found at [docs/windows-install-guide.md](docs/windows-install-guide.md)

1. Clone the project
2. Install the project dependencies.
3. Load database
