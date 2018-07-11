CloudServer Application Service

* Install 

<pre>
php composer.phar install
</pre>

* Run

<pre>
# cp wsserver.service /etc/systemd/system
# systemctl enable wsserver
# systemctl start wsserver
</pre>

* CloudServer Protocol

In order to establish a connection with the CloudServer a client library fisrt needs to create a websocket connection to the server. An URL for such connection might look like <i>wss://ws.cloudserver.com/ws</i>

After establishing a connection client library may send and receive messages structured in packets in JSON format. The structure of reply packets is:

<pre>
{
"result" : &lt;string&gt;
"type" : &lt;interger&gt;
"data" : &lt;string&gt;
}
</pre>

An example of the successful packet:

<pre>
{
"result" : "success",
"type" : 2,
"data" : "crocodile"
}
</pre>

If a error occurs on the server side the packet structure will be as this:

<pre>
{
"result" : "error"
"message" : &lt;string&gt;
}
</pre>




Type of the packet could be one of the following:

<pre>
1 - INIT (from client to server). Used by a client to obtain a word. It does not
2 - WORD (from server to client). The reply from the server containing the word
3 - COINS (from client to server). Client sends coins with this request. "stack" field should contain an escaped stack of coins. "word" field should contain a recipient's word.
4 - PROGRESS (from server to client). Server sends the progress of powning. 
5 - DONE (from server to client). Server sends this when powning is done. The response might have a hash if the sender requested a change.
8 - HASH (from server to client). Server sends a hash for a client where it can download powned coins
</pre>


A typical workflow would look like this:

1. Client connects to the server by the means of WebSocket protocol

2. Client sends INIT request and gets its WORD

3. Client listens for the servers packet in a loop

4. If the client receives HASH packet he needs to download the coins from the following url <i>https://cloudserver.domain/cc.php?h=hash</i>. The link has a secret random number and will be valid only for 10 minutes.

5. If the client wants to transfer coins to someone it needs to send COINS packet with the escaped JSON stack in the "data" field of the packet.

6. After sending coins the client may receive PROGRESS packets reporting the progress of the pownign process. Finailly it will receive DONE packet with a hash or ERROR (if powning failed)

7. The sender may use the hash in the previous step to download his change if he had sent not the whole stack to the recipient. The url for downloading is the same as in the step 4: <i>https://cloudserver.domain/cc.php?h=hash</i>



An example of COINS packet

<pre>
{"type":3,
"word":"hippoman",
"stack":"{\"cloudcoin\": [{\"ed\": \"02-2018\", \"aoid\": [], \"sn\": \"14966850\", \"nn\": \"1\", \"an\": [\"f4166084f94f69deb65bd2e29dbc05f7\", \"0603f30fd5dd36fb83cffcf0d8d5caf2\", \"bdb804b03f247994b7befce9cd377e13\", \"a0c8b899674fa5e817739221062962be\", \"4daeb3fa37602bbb0ae2b74246e3f02e\", \"c6108bca7ec7645f634a5835c6b72fba\", \"c230628282a22d549b49412f63beac87\", \"eff741952c821e630dec9ac7d3b37aa8\", \"c64901fb2fee825496407abf6d545826\", \"0ba7d97f33942e29fb6737c89b080de0\", \"ffb392d9ca5c5eafa2518da16621f47c\", \"8d8184916629471860074f664ba69524\", \"20b4c706492d3175ffbe90b4200708c4\", \"c5ea646a2ea4d2b1fd7416255e6dbb03\", \"500ae7cbb73cff943612bb806e5a5b37\", \"ba2f693e28362b03deb3518751db82a0\", \"14e55596181607f1dca6001362e04e13\", \"b2d170f3b8c2264181135aa5f96dc609\", \"9ae1f28cb0cea1935b0f349ee1c2225b\", \"80b4eb77584a33a460a9e80a8dac5dc6\", \"6c2f0a18ed3260902142c66a61c1f08d\", \"9c4f723f04c717798a4e5a7864d521c5\", \"b1c07e448e31a4f7023a4e1a51c04e98\", \"de6b62b018d9da060ed10cb798d75409\", \"27a10f7607c965b8ac3e43ad439d3251\"]}, {\"ed\": \"05-2018\", \"aoid\": [], \"sn\": \"14969243\", \"nn\": \"1\", \"an\": [\"29ba797c71c104ce60ccbea85603338f\", \"12dcb26b6f85784887fec8617903a097\", \"48f9e552e9bb789557d912a3d2e6dcfe\", \"6f70db77aca32282d462906817f5ae18\", \"20c5eeedb1a6b182f1d06b8b81cf20cf\", \"915ef2e573bce39930961672fb7dacc3\", \"118685d8a2e69c54e09ba4bba1ee3cad\", \"85bbbd7c66a98131c91628c8dcbf3598\", \"f0163eadf42e6603e3ff6d52070f0d76\", \"6b57e6104ef96c48974a2f771a53ce64\", \"af0e28030e9a9bf3859e974cb17a8339\", \"024077a826b8091b76ae983863cdb76d\", \"d27a08d76caac29c9c7d736c9bc66207\", \"e80847bd2faeeb83698c5f8d360aa026\", \"4ce23b8ef6e77c79453e2137d2a6772d\", \"76f8fcc8a1ea72137c9e1c9d6e7544a2\", \"156b7ce7999e89ddd340319f9225007e\", \"b58df1f5d44f3d6ea5a53bc8028a9620\", \"6273ad9d970ea6e5543173a30c6fe842\", \"17d4a4cc67f44ccf9d07acf98f920a14\", \"29b8bfb5edc2014a81e26204732fffb5\", \"06ee3abd25e3c14315f3292dc4c84cc0\", \"1e9f59f428417c842216171a7fc7b69a\", \"1c4fd497fdeb5dea1fb01f03646dd2ba\", \"685fac7d9b28b8d755caa90c92fee8b8\"]}, {\"ed\": \"04-2018\", \"aoid\": [], \"sn\": \"14967933\", \"nn\": \"1\", \"an\": [\"05e68141c456041c28c2746548e70cd3\", \"a0bb51cb272d0c1af258f8aa8ddf7ecf\", \"6087e8deb0cd734c9bb964c5d58d0fdc\", \"c9ffb1df0e93da7e55d4106facdb9dca\", \"25244d0fdbed6d477ee48d3a932866de\", \"549aee6477920f8e78948c89c6fec4ac\", \"97b0b2b2bf754d4b268366b7c337de22\", \"179e579c19c0d1c8f30074f25d66c528\", \"820d861148373a6e83373f04932a271f\", \"8710dad5b958961960e2dcb98636fc4c\", \"01031ebae990a223e054c058bd405ad3\", \"e7d3ddef583644e1db588ff0dbf83814\", \"12ff5691ad7dcdd9076ea0962fbb21d5\", \"24a6184ee0fcab3e5e561f5c46d45d02\", \"b0a7157a7d2a0ad23eef6c7c4e021244\", \"70801bc4ee6825eedb4b3b9a0e408365\", \"8802b70de2a35c4194229f104fbb2dda\", \"eff7fd0b93d20383ba4483c167e9d12d\", \"40fb522122119b79f97a3f98e6c2e957\", \"6e7a027a2439094daa9c24ab8ca82f93\", \"954b35bb09bb9509af22caaa28d239d3\", \"384bcd790320ec694ba26a022694a079\", \"28b84df079acba59f4324d68ae62f259\", \"ef721231a46d29b06f8d90edb6b23438\", \"5af0e96ef0bdeef8854531a8605a52ff\"]}, {\"ed\": \"01-2018\", \"aoid\": [], \"sn\": \"14964543\", \"nn\": \"1\", \"an\": [\"fcc84a4bccb0c7f26bf5c1aac6135c59\", \"74fa1f18ee3da1a484c9bd52a5861d44\", \"943add1962bbea57e933653128020cad\", \"2e9b15f2a5c2d881d59844666732835e\", \"42b98f29e58ec89fefa5c7352b3bda63\", \"6cc23ae5354499c851ee31412bb49ebe\", \"6d3014424686d277b344caa4d5663a10\", \"5de4893b853268417cf696f9fdc25439\", \"4660594cf4c8f72b0ddecf772192bcea\", \"2add8b37dee00ba2af8de9613218a0c7\", \"e309a30120b0f53a9bd9c0291675e562\", \"f401d7e14546bf3952be09699628b430\", \"f0f40bb3decb9154054ead3a3f9afa7f\", \"2e9bbb52d3c398c8932cb88f3f5d16f7\", \"23e0b032ff527e45bd57ba98c0eccc4b\", \"fd6929b4c11eb0f8daed8566eb6304ab\", \"6f2741d6ac444b4be733920e82adec2c\", \"bd65ab2b9e965da5f20af531eca7de61\", \"2f4603da67cfb1d00a3d37d9c70dd9c9\", \"9e9d6fcf7baf45a2bf549222648de2d9\", \"d51a06ba20b2a8898d107cede29404cf\", \"56d8bc46450b93533425a54a4c359417\", \"b902d3a9e8753bb08a94aa2791fb7323\", \"0575eff30e0595aa8b55c302ae55342e\", \"b99a82be76ac03c78f954e98bae4474e\"]}, {\"ed\": \"02-2018\", \"aoid\": [], \"sn\": \"14966209\", \"nn\": \"1\", \"an\": [\"f308812c7709cc794e4a8413d01ec186\", \"8a169a70173704e61a014df3b86fea2f\", \"f31ba41c637cdd4708fc0600ec934fd6\", \"5de3daf6023b78a146a580663282a1a4\", \"161c7c21870aba8e3c70de3fb7876642\", \"3cce61ee07f0203f203b18de084dda6f\", \"ecdca27a3bf6f77fb51131fe8ce6d824\", \"45934e1190488779fe3655c7c4825cf1\", \"20550d8d753e4d2b9796a65e10546616\", \"d24ad13a8db3f56d22383e3656278a6b\", \"cf652e3d90a06ff4b552eb1e00f2c76e\", \"d075857b4f640add427f915958fe6582\", \"2c18f6780c7b1ebb0750f6b8e6a4ac50\", \"1343e6d8c38fc143e38e407350d1457e\", \"c72dad7de2212aa0d86e49b6676bb8fe\", \"ed5d94b13c147c0a0de634149d1dc8aa\", \"b950f45593aad019fcbbf90f340e6a2b\", \"a963abc71566182c0c882e5cf0063faa\", \"8f95034cf273055ef6445ffc385a4c13\", \"361e51f4decd4e9412935b8222a4f5a4\", \"34531a17b63ef60ac7e386c87dfee1b3\", \"1259c8f70e2896864264abc238c9b021\", \"abd3b74fe3367b876c439de45e00bbc7\", \"c980723df636c577c0bb1d127cd9d02a\", \"178f2ee5229cb630ac8d50ac170a8cfa\"]}]}"
}
</pre>

An exapmle of the DONE packet
<pre>
{
"result" => "success",
"type" => 5,
"data" => "MjAxOC0wNy0xMC9jZTE2NDA2NWU4YmFmMmMyNzY1ZDkzY2Y1MTk0YmQxOC5wb3duZWQuMTUzMTIzMTk0OS4zOTAyMDUuc3RhY2s="
}
</pre>

