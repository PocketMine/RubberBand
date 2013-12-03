# ![](icon.png) RubberBand _for [PocketMine-MP](https://github.com/PocketMine/PocketMine-MP)_

__A multithreaded frontend proxy with multiple servers, lobbies and load balancing.__


### Features

* Multiple frontend receive workers.
* One send worker per server.
* Assign each server to a group to load balance between them, for example, multiple lobbies so you can always accept new players.
* Seamless server-to-server transfer.
* Proxy only needs a single config, an API key. Everything else is done by the servers.
* Servers only need a plugin, no source changes. Also, it includes an API to extend its functionality.
* Servers get autoregistered, so they can start accepting players directly.
* Global / per server player list accesible.


```
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
```	


### Description

RubberBand is the work of a server plugin and a standalone proxy, enabling the creation of dynamic server networks.
The proxy handles all the incoming packets, and decides where to send it depending on the current state of the player. 
On server command, the proxy will cease connection with the server and open a new connection to the target server, sending all the current connection status so the player doesn't have to log out.

Servers can be grouped, for example, you can have 8 lobby servers grouped into the _lobbyServers_ group. When a new player connects, instead of being redirected to the first lobby server, he will be sent to the least used one.

The proxy configuration is given by the servers itself, so you can create new servers on the fly and they will be added to the RubberBand proxy. When removing them, they will be removed after 0-10 seconds from the proxy.

You can also issue messages to be broadcasted to all servers, teleport players using commands, get info about other servers... And RubberBand comes with an API for plugins so you can extend its functionality.


__Expected release date: 10th of December 2013__



* __RubberBand__ uses the [pthreads extension for PHP](https://github.com/krakjoe/pthreads) by _[krakjoe](https://github.com/krakjoe)_