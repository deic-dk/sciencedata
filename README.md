## The data service

- built on ownCloud/Nextcloud
- using apps and a highly customized theme, i.e. not touching the ownCloud/Nextcloud core
- the core apps are interdependent, and should be installed all together
- moreover, they should be accompanied by the theme

The core apps are:

- [user_saml](#deic-dk/user_saml)
- chooser
- files_sharding
- user_group_admin
- files_accounting
- user_notification

The theme is:

- deic_oc7_theme

If you want to go ahead and create a similar service, you need to install all of these apps together
and modify the theme to match your organization look and feel and other requirements.

## The compute service

- built on hardware and middleware from origo.io
- integrated with the data service via a common permission system allowing seamless data flow between a user's VMs and the data service
- provides apps that encapsulate fully contextualized compute clusters, ready to run on a given research problem
- provides an app store for such apps
