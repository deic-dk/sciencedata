<!DOCTYPE html>
<html>
	<head>
		<title>Caddy works!</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="icon" href="data:,">
		<style>
			* {
				box-sizing: border-box;
				padding: 0;
				margin: 0;
			}

			body {
				background: #f1f4f5;
				font-family: sans-serif;
				font-size: 20px;
				-webkit-font-smoothing: antialiased;
			}

			a {
				color: #2f79ff;
				text-decoration: none;
			}

			a:hover {
				text-decoration: underline;
			}

			.stack {
				width: 70%;
				max-width: 1150px;
				margin: 25px 0 150px 25px;
				display: flex;
				align-content: flex-start;
			}

			.paper {
				position: relative;
				flex-shrink: 0;
				width: 100%;

				background: white;
				border-radius: 3px;
				box-shadow: 1px 2px 4px 1px rgba(0, 0, 0, .15);
				padding: 100px;
			}

			#paper1 {
				top: 45px;
				left: 50px;
			}

			#paper2 {
				top: 20px;
				left: -100%;
			}

			#paper3 {
				top: 25px;
				transform: rotate(-4deg);
				left: -200%;
			}

			#caddy {
				max-width: 175px;
				/*margin-bottom: 75px;*/
			}

			#caddy .caddy-color {
				fill: #005e7e;
			}

			h1 {
				font-size: 20px;
				margin-bottom: 50px;
			}

			h1 .emoji {
				font-size: 150%;
				vertical-align: middle;
			}

			h1 .lang {
				margin-right: 1.5em;
			}

			h2 {
				font-size: 28px;
				margin-top: 1.5em;
			}

			p,
			ol,
			ul {
				color: #333;
				line-height: 1.5em;
			}

			p {
				margin: 1.5em 0;
			}

			ol,
			ul {
				margin: .5em 0 .5em 2em;
			}

			ol li,
			ul li {
				margin-left: 1em;
				margin-bottom: .5em;
			}

			li ol {
				list-style-type: lower-alpha;
			}

			code {
				color: #000;
				font-family: Menlo, monospace;
				background: #f0f0f0;
				border-radius: 2px;
				padding: 4px 8px;
				font-size: 90%;
			}

			.warn {
				color: #dd0000;
			}

			footer {
				color: #777;
				font-size: 16px;
				text-align: center;
				max-width: 600px;
				margin: 0 auto 50px;
			}

			#disclaimer {
				font-size: 14px;
				margin-top: 20px;
				display: inline-block;
				border-top: 1px solid #ccc;
				padding: 20px;
			}

			header, header a {
				font-size: 50px;
				margin-bottom: 50px;
				color: #005e7e;
			}
			sup {
    		font-size: 12px;
    		vertical-align: text-top;
  		}

			@media (max-width: 1100px) {
				.stack {
					width: 85%;
				}
			}

			@media (max-width: 800px) {
				.stack {
					margin: 0 0 50px 0;
					width: 100%;
				}

				.paper {
					padding: 50px;
				}

				#paper1,
				#paper2 {
					display: none;
				}

				#paper3 {
					left: 0;
					transform: none;
					top: 0;
				}

				#caddy {
					margin-bottom: 25px;
				}
			}
		</style>
	</head>
	<body>
		<div class="stack">
			<div class="paper" id="paper1"></div>
			<div class="paper" id="paper2"></div>
			<div class="paper" id="paper3">
				<header><a href="https://caddyserver.com/" title="Caddy Web Server">Caddy<sup>&reg;</sup></a>
				on <a href="https://sciencedata.dk/">ScienceData</a></header>
				<h1>
					<!-- English --> <span class="lang">Congratulations!</span>
					<!-- Japanese --> <span class="lang">おめでとう!</span>
					<!-- Spanish --> <span class="lang">Felicidades!</span>
					<!-- Chinese --> <span class="lang">恭喜!</span>
					<!-- Hindi --> <span class="lang">बधाई हो!</span>
					<!-- Russian --> <span class="lang">Поздравляю!
</span>
					<span class="emoji">🎊</span>
				</h1>

				<p>
					Your <?php printf('PHP-enabled');?> web server is working. Now make it work for you 💪
				</p>
				<p>
					Caddy is ready to serve your site:
				</p>
				<ol>
					<li>Fire up your favorite file-transfer client (we recommend <a href="https://cyberduck.io/">CyberDuck <img src="//cdn.cyberduck.io/img/cyberduck-icon-64.png" class="img-responsive" title="Cyberduck" width="18px" height="18px"></a>) and point it to
						<ul>
							<li><code>https://<?php printf(trim(`cat /tmp/public_home_server`));?>/storage/</code></li>
							<!--<li><code>sftp://kube.sciencedata.dk:<?php printf(`printf $SSH_PORT`);?>/var/www/<?php printf(trim(`hostname`));?>/</code></li>-->
						</ul>
					</li>
					<li>Make sure you choose WebDAV (HTTPS) as protocol, then log in with your ScienceData username and <a href="https://sciencedata.dk/sites/user/ManagingFiles/index#toc_head11">device password</a>.
					<!--In the second case, log in with username <code>www</code> and your SSH key.-->
					</li>
					<li>Navigate to your chosen storage folder and upload your site's files.
					<li>Visit your site!</li>
				</ol>
				<h2>If that worked 🥳</h2>
				<p>
					Awesome! You won't have to look at this slanted page anymore.
				</p>
				<p>
					The web server we use is called Caddy. This site is served by one Caddy server over HTTP to another Caddy server,
					running as reverse proxy on kube.sciencedata.dk and serving over HTTPS to the outside world.
					You can read more about Caddy in the <a href="https://caddyserver.com/docs/">📖 Caddy documentation</a>. Have fun!
				</p>

				<h2>If that didn't work 😶</h2>
				<p>
					It's okay, we can fix it! First check the following inside your container:
				</p>
				<ul>
					<li>Check if <code>/var/www</code> is NFS-mounted inside your container: Execute <code>df -h</code>.</li>
					<li>Check your Caddy and PHP logs, <code>/var/log/caddy.log</code> and <code>/var/log/php*-fpm.log</code>, for errors.</li>
					<li>Are your site's files readable by the user www? <code>ls -la /var/www/</code>.</li>
				</ul>
				<p>
					If you're still stuck, send us an <a href="mailto:support@sciencedata.dk">email</a> with the above information and we'll help you out.
				</p>
			</div>
		</div>

		<footer>
			&copy; Copyright 2021 ScienceData.
			<br>
			ScienceData is a a family of services provided by DTU <br>
			- the Technical University of Demnark.

			<div id="disclaimer">Neither DTU, ScienceData or the Caddy project is responsible for the content, disposition, or behavior of this Web property, which is independently owned and maintained. For inquiries, please contact the site owner.</div>
		</footer>

	</body>
</html>
