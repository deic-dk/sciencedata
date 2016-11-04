## Data service

- built on ownCloud/Nextcloud
- using apps and a highly customized theme, i.e. not touching the ownCloud/Nextcloud core
- the core apps are interdependent, and should be installed all together
- moreover, they should be accompanied by the theme

Core apps:

- [user_saml](https://github.com/deic-dk/user_saml)
- [chooser](https://github.com/deic-dk/chooser)
- [files_sharding](https://github.com/deic-dk/files_sharding)
- [user_group_admin](https://github.com/deic-dk/user_group_admin)
- [files_accounting](https://github.com/deic-dk/files_accounting)
- [user_notification](https://github.com/deic-dk/user_notification)

Theme:

- deic_oc7_theme

If you want to go ahead and create a similar service, you need to install all of these apps together
and modify the theme to match your organization look and feel and other requirements.

## Compute service

- built on hardware and middleware from origo.io
- integrated with the data service via a common permission system allowing seamless data flow between a user's VMs and the data service
- provides apps that encapsulate fully contextualized compute clusters, ready to run on a given research problem
- provides an app store for such apps
