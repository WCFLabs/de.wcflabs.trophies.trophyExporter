#!/bin/sh

#define package
PACKAGE_NAME=${PWD##*/}

# build package.xml
datum=$(date +"%Y%m%d%H%M")
cp package.xml tmp_package.xml

## !!!!!!!!!!!!!!!!
## use versionnumber instead of the Alpha-Build number!
## The script replaces the number automatically. 
## !!!!!!!!!!!!!!!!
sed -e "s/versionnumber/$datum/g" package.xml > tmp.xml
rm package.xml
cp tmp.xml package.xml
rm tmp.xml

# build package
[ -z $PACKAGE_NAME ] && exit 1
test -e acptemplates && echo "\nBuilding acptemplates.tar\n-------------------------" && cd acptemplates && tar cvf ../acptemplates.tar --exclude=".*" -- * && cd ..
test -e files && echo "\nBuilding files.tar\n------------------" && cd files && tar cvf ../files.tar --exclude '.git*' --exclude '*.xcf' --exclude '*.coffee' --exclude=".*" -- * && cd ..
test -e templates && echo "\nBuilding templates.tar\n----------------------" && cd templates && tar cvf ../templates.tar --exclude=".*" -- * && cd ..
test -e acptemplate && echo "\nBuilding acptemplate.tar\n-------------------------" && cd acptemplate && tar cvf ../acptemplate.tar --exclude=".*" -- * && cd ..
test -e file && echo "\nBuilding file.tar\n------------------" && cd file && tar cvf ../file.tar --exclude '.git*' --exclude '*.coffee' --exclude=".*" -- * && cd ..
test -e template && echo "\nBuilding template.tar\n----------------------" && cd template && tar cvf ../template.tar --exclude=".*" -- * && cd ..
test -e wcf_files && echo "\nBuilding wcf_files.tar\n----------------------" && cd wcf_files && tar cvf ../wcf_files.tar --exclude '.git*' --exclude '*.coffee' --exclude=".*" -- * && cd ..

echo "\nBuilding $PACKAGE_NAME.tar"
tar cvf $PACKAGE_NAME.tar --exclude=".*" --exclude acptemplates --exclude README.md --exclude build.sh --exclude files --exclude wcf_files --exclude templates --exclude acptemplate --exclude '._*' --exclude file --exclude template --exclude nbproject --exclude tmp_package.xml --exclude test -- *

test -e acptemplates.tar && rm acptemplates.tar
test -e files.tar && rm files.tar
test -e templates.tar && rm templates.tar
test -e acptemplate.tar && rm acptemplate.tar
test -e file.tar && rm file.tar
test -e template.tar && rm template.tar

# restore version 
rm package.xml
cp tmp_package.xml package.xml
rm tmp_package.xml

exit 0