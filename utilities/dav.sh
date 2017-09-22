#!/bin/bash
#
# List, download from and upload files and directories to WebDAV servers, using curl.
# Copyright, 2016, Frederik Orellana
#
# Usage:
#        Uploading:
#
#        dav.sh [-u user [-p password]] [-c certificate -k key] [-f] [-v] /some/local/file_or_dir https://my.server/some/directory/
#
#        Downloading:
#
#        dav.sh [[-u user] [-p password]] [-c certificate -k key] [-f] [-v] https://my.server/some/directory/file_or_dir /some/local/dir/
#
#        Listing:
#
#        dav.sh [-u user [-p password] [-c certificate -k key] [-f] [-v] https://my.server/some/directory/
#
# Options
#
#        -f: overwrite existing files
#
#        -v: be verbose
#
# Dependencies: curl, xmllint
#

MAX_RUNNING_TRANSFERS=10

while getopts "u:p:c:p:fv" flag; do
	case "$flag" in
		u)
			user=$OPTARG
			;;
		p)
			pass=$OPTARG
			;;
		c)
			cert=$OPTARG
			;;
		k)
			key=$OPTARG
			;;
		f)
			force="yes"
			;;
		v)
			verbose="yes"
			;;
		\?)
			echo "Invalid option: -$OPTARG" >&2
			exit 1
		;;
		:)
			echo "Option -$OPTARG requires an argument." >&2
			exit 1
		;;
	esac
done

arg1=${@:$OPTIND:1}
arg2=${@:$OPTIND+1:1}


if [[ $arg1 =~ https*://.* ]]; then
	url=$arg1
fi

if [[ ! -z "$arg2" ]]; then
	if [[ $arg2 =~ https*://.* ]]; then
		if [ ! -z "$url" ]; then
			echo "Cannot do local or third-party operations." >&2
			exit 1
		fi
		url="$arg2"
		in_dir="$arg1"
		upload="yes"
	else
		url="$arg1"
		out_dir="$arg2"
		download="yes"
	fi
fi

if [ -z "$url" ]; then
	echo "Usage: dav.sh [-u user [-p password]] [-c certificate -k key] source destination" >&2
	exit 1
fi

user_pass=""
if [ "$user" != "" ]; then
	user_pass="--user $user:$pass"
fi

cert_key=""
if [ "$cert" != "" ]; then
	if [ "$key" = "" ]; then
		echo "Usage: dav.sh [-u user [-p password]] [-c certificate -k key] source destination" >&2
		exit 1
	fi
	cert_key="--cert $cert --key $key"
fi

### From http://mohan43u.wordpress.com/?s=url+decoding

function urlencode()
{
echo "$@" | sed -e's/./&\n/g' -e's/ /%20/g' | grep -v '^$' | while read CHAR; do
	test "${CHAR}" = "%20" && echo "${CHAR}" || echo "${CHAR}" | grep -E '[-[:alnum:]!*.'"'"'()]|\[|\]' || echo -n "${CHAR}" | od -t x1 | tr ' ' '\n' | grep '^[[:alnum:]]\{2\}$' | tr '[a-z]' '[A-Z]' | sed -e's/^/%/g'
done | sed -e's|%2F|/|g' | tr -d '\n'
}

function urldecode()
{
echo "$@" | sed -e's/%\([0-9A-F][0-9A-F]\)/\\\\\x\1/g' | xargs -n1 echo -e | sed -e's/+/ /g'
}

# Recursive ls the hard way - NOT USED
function davls(){

	local base=`echo $1 | sed -r 's|^(https*://*[^/]*)(/.*)$|\1|'`
	local path=`echo $1 | sed -r 's|^(https*://*[^/]*)(/.*)$|\2|'`
	
	local encoded_path=`urlencode "$path"`

	curl -k -L --request PROPFIND --header "Depth: 1" $user_pass $cert_key \
	"${base}$encoded_path" 2>/dev/null | \
	xmllint --format - 2>/dev/null | grep href | sed -r 's|^ *||' | \
	sed -r 's|<d:href>(.*)</d:href>|\1|' | sed -r "s|^/$encoded_path/*||" | \
	grep -v '^\./$' | grep -v '^$' |

	while read name; do
		if [[ $name =~ .*/$ ]]; then
			davls "${base}/${path}/${name}"
		else
			echo "${base}/${path}/${name}"
		fi
	done

}

# Recursive ls the easy way
function davrls(){

	local base=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	
	local encoded_path=`urlencode "$path"`
	encoded_path=`echo $encoded_path | sed 's|/$||g'`
	
	local exclude=$encoded_path
	if ( ! davisdir "${base}/${encoded_path}" ); then
		local exclude=`echo "$exclude" | sed -r 's|(.*)/([^/]*)$|\1|g'`
	fi
	
	[ "$verbose" == "yes" ] && echo curl -k -L --request PROPFIND --header \"Depth: infinity\" \
	$user_pass $cert_key \"${base}/${encoded_path}\"
	curl -k -L --request PROPFIND --header "Depth: infinity" $user_pass $cert_key \
	"${base}/${encoded_path}" 2>/dev/null | \
	xmllint --format - 2>/dev/null | grep href | sed -r 's|^ *||' | \
	sed -r 's|<d:href>(.*)</d:href>|\1|' | sed -r "s|^/$exclude/||" | \
	grep -v '^\./$' | grep -v '^$' | \
	while read name; do
		echo $name
	done
}

function davexists(){
	local base=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	local encoded_path=`urlencode "$path"`
	encoded_path=`echo $encoded_path | sed 's|/$||g'`
	var=`curl -k -L --header "Depth: 1" --request PROPFIND $user_pass $cert_key \
	"${base}/${encoded_path}" 2>/dev/null | \
	xmllint --format - 2>/dev/null | grep href | sed -r 's|^ *||' | \
	sed -r 's|<d:href>(.*)</d:href>|\1|' | grep "^/$encoded_path/*$"`
	if [ -z $var ]; then
		return 1
	else
		return 0
	fi
}

function davisdir(){
	local base=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	local encoded_path=`urlencode "$path"`
	encoded_path=`echo $encoded_path | sed 's|/$||g'`
	var=`curl -k -L --header "Depth: 1" --request PROPFIND $user_pass $cert_key\
	"${base}/${encoded_path}/" 2>/dev/null | \
	xmllint --format - 2>/dev/null | grep href | sed -r 's|^ *||' | \
	sed -r 's|<d:href>(.*)</d:href>|\1|' | grep "^/$encoded_path/$"`
	if [ -z $var ]; then
		return 1
	else
		return 0
	fi
}

# mkdir remotely - NOT USED
function davmkdir(){
	local base=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	
	curl -k -L $user_pass $cert_key --request MKCOL "${base}/${path}/"
	return $?
}

# mv remotely - NOT USED
function davmove(){
	local base0=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path0=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	local encoded_path0=`urlencode "$path0"`
	encoded_path0=`echo $encoded_path0 | sed 's|/$||g'`
	local base1=`echo $2 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path1=`echo $2 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	local encoded_path1=`urlencode "$path1"`
	encoded_path1=`echo $encoded_path1 | sed 's|/$||g'`
	
	curl -k -L $user_pass $cert_key --request MOVE --header "Destination: ${base1}/${encoded_path1}" \
	"${base0}/${encoded_path0}"
	return $?
}

function davpull(){
	local base=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path=`echo $1 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	path=`echo $path | sed 's|/$||g'`
	local encoded_path=`urlencode "$path"`
	
	if ( davisdir "${base}/${encoded_path}" ); then
		oneoff="no"
		local base_dir=`echo "$path" | sed -r 's|.*/([^/]*)$|\1|g'`
		base_dir=`echo $base_dir | sed -r 's|/$||g'`
	else
		if [ -z "$3" ]; then
			oneoff="yes"
		fi
	fi
	
	local out_dir="${2}"
	if [ -z "$3" ]; then
		local out_dir="${out_dir}/${base_dir}"
	fi
	
	out_dir=`echo $out_dir | sed -r 's|//*$||g'`
	out_dir=`echo $out_dir | sed -r 's|///*|/|g'`
	
	# Create output dir if missing
	ls "$out_dir" >& /dev/null || ( echo mkdir "$out_dir" && mkdir "$out_dir" ) || exit $?
	
	curl -k -L --header "Depth: 1" --request PROPFIND $user_pass $cert_key "${base}/${encoded_path}" 2>/dev/null | \
	xmllint --format - 2>/dev/null | grep href | sed -r 's|^ *||' | \
	sed -r 's|<d:href>(.*)</d:href>|\1|' | sed -r "s|^/$path/$base_dir/||" | sed -r "s|^/$encoded_path/$base_dir/||" | \
	grep -Ev '^\./$' | grep -Ev '^$' |

	while read name; do
		decoded_name=`urldecode "$name"`
		if [ $name == "$base_dir/" ]; then
			continue
		fi
		if [[ $name =~ .*/$ ]]; then
			[ "$verbose" == "yes" ] && echo "getting ${base}/${encoded_path}/${name} --> ${out_dir}/${decoded_name}" >&2
			davpull "${base}/${encoded_path}/${name}" "${out_dir}/${decoded_name}" "no"|| exit $?
		else
			local running_curls=`ps auxw | grep -v grep | grep curl | wc -l`
			if [ "$oneoff" == "yes" ]; then
				src="${base}${name}"
				dest="${out_dir}${decoded_name}"
				path=`echo $path | sed -r 's|/[^/]*$||'`
				dest=`echo $dest | sed "s|^${out_dir}/${path}|${out_dir}|"`
			else
				src="${base}/${encoded_path}/${name}"
				dest="${out_dir}/${decoded_name}"
			fi
			[ "$verbose" == "yes" ] && echo curl --progress-bar -k -L $user_pass $cert_key \"${src}\" \> \"${dest}\"
			if ( ls "${dest}" >& /dev/null && [ "$force" != "yes" ] ); then
				echo " ... exists" >&2
				continue
			elif [ $running_curls -gt $MAX_RUNNING_TRANSFERS ]; then
				( curl --progress-bar -k -L $user_pass $cert_key "${src}" > "${dest}" 2>/dev/null && echo " done" )
			else
				( curl --progress-bar -k -L $user_pass $cert_key "${src}" > "${dest}" 2>/dev/null && echo " done" ) &
			fi
		fi
	done
}

function davpush(){
	local  in_dir="$1"
	in_dir=`urldecode "$in_dir"`

	if [ -d "$in_dir" ]; then
		oneoff="no"
		in_dir=`echo $in_dir | sed -r 's|/$||g'`
		local base_dir=`echo "$in_dir" | sed -r 's|.*/([^/]*)$|\1|g'`
		local encoded_base_dir=`urlencode $base_dir`
		encoded_base_dir=`echo $encoded_base_dir | sed -r 's|/$||g'`
	else
		if [ -z "$3" ]; then
			oneoff="yes"
		fi
	fi

	local base=`echo $2 | sed -r 's|^(https*://*[^/]*)/(.*)$|\1|'`
	local path=`echo $2 | sed -r 's|^(https*://*[^/]*)/(.*)$|\2|'`
	path=`urldecode "$path"`
	path=`echo $path | sed 's|/$||g'`
	local encoded_path=`urlencode "$path"`

	if ( ! davexists "${base}/${encoded_path}/${encoded_base_dir}" ); then
		echo "Creating missing directory ${base}/${encoded_path}/${encoded_base_dir}" >&2
		davmkdir "${base}/${encoded_path}/${encoded_base_dir}" || exit $?
	fi

	ls "$in_dir" | \
	grep -Ev '^\./$' | grep -Ev '^$' |

	while read name; do
		encoded_name=`urlencode "$name"`
		if [ -d "${in_dir}/${name}" -a oneoff != "yes" ]; then
			[ "$verbose" == "yes" ] && echo putting $user_pass "${in_dir}/${name}" to "${base}/${encoded_path}/${encoded_base_dir}/${encoded_name}" >&2
			davpush "${in_dir}/${name}" "${base}/${encoded_path}/${encoded_base_dir}" "no" || exit $?
		else
			local running_curls=`ps auxw | grep -v grep | grep curl | wc -l`
			if [ "$oneoff" == "yes" ]; then
				src="${in_dir}"
				encoded_name=`echo "$encoded_name" | sed -r 's|.*/([^/]*)$|\1|g'`
				dest="${base}/${encoded_path}/${encoded_base_dir}${encoded_name}"
			else
				src="${in_dir}/${name}"
				dest="${base}/${encoded_path}/${encoded_base_dir}/${encoded_name}"
			fi
			[ "$verbose" == "yes" ] && echo curl --progress-bar --request PUT -k -L $user_pass $cert_key --upload-file \"${src}\" \"${dest}\"
			if ( davexists "${dest}" >& /dev/null && [ "$force" != "yes" ] ); then
				echo " --> exists" >&2
				continue
			elif [ $running_curls -gt $MAX_RUNNING_TRANSFERS ]; then
				( curl --progress-bar --request PUT -k -L $user_pass $cert_key --upload-file \
				"${src}" "${dest}" 2>/dev/null && echo " done" )
			else
				( curl --progress-bar --request PUT -k -L $user_pass $cert_key --upload-file \
				"${src}" "${dest}" 2>/dev/null && echo " done" ) &
			fi
		fi
	done
}


if [ -n "$download" ]; then
	davpull "$url" "$out_dir/"
elif [ -n "$upload" ]; then
	davpush "$in_dir" "$url"
else
	davrls "$url"
fi
