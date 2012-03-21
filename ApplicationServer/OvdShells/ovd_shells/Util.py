# -*- coding: utf-8 -*-

# Copyright (C) 2010-2012 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2010, 2011, 2012
# Author David LECHEVALIER <david@ulteo.com> 2011
# Author Thomas MOUTON <thomas@ulteo.com> 2012
#
# This program is free software; you can redistribute it and/or 
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2
# of the License
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import base64
import glob
import os
import sys

from ovd_shells.Platform import _platform as Platform
from ovd_shells.Platform.Novell import Novell

def redirect_to_dump(purge = True):
	path = os.path.join(Platform.getUserSessionDir(), "dump.txt")
	try:
		dirname = os.path.dirname(path)
		if not os.path.exists(dirname):
			os.makedirs(dirname)
		
		if purge is True:
			mode = "w"
		else:
			mode = "a"
		
		buf = file(path, mode, 0)
	except IOError, err:
		return
	
	sys.stderr = buf
	sys.stdout = buf
	print "#################################################"


def loadUserEnv(d):
	path = os.path.join(d, "env")
	try:
		f = file(path, "r")
	except:
		return
	
	lines = f.readlines()
	f.close()
	
	for line in lines:
		line = line.strip()
		try:
			key,value = line.split("=", 1)
		except:
			continue
		
		os.environ[key] = value

def manageAutoStartApplication(d, im):
	for path in glob.glob(os.path.join(d, "to_start", "*")):
		f = file(path, "r")
		lines = f.readlines()
		f.close()
		
		try:
			app_id = int(lines[0].strip())
		except ValueError, err:
			print "Invalid application id '%s'"%(lines[0].strip())
			continue
		
		if len(lines)>1:
			d = base64.decodestring(lines[1].strip())
			
			o = d.splitlines()
			if len(o) == 3:
				f_type = o[0]
				path = o[1]
				share = o[2]
				
				if f_type == "native":
					dir_type = im.DIR_TYPE_NATIVE
				elif f_type == "shared_folder":
					dir_type = im.DIR_TYPE_SHARED_FOLDER
				elif f_type == "rdp_drive":
					dir_type = im.DIR_TYPE_RDP_DRIVE
				elif f_type == "known_drives":
					dir_type = im.DIR_TYPE_KNOWN_DRIVES
				elif f_type == "http":
					dir_type = im.DIR_TYPE_HTTP_URL
				else:
					print "manageAutoStartApplication: unknown dir type %X"%(f_type)
					return
			
			else:
				dir_type = im.DIR_TYPE_NATIVE
				share = None
				path  = lines[1].strip()
			
			im.start_app_with_arg(None, app_id, dir_type, share, path)
			continue
		
		im.start_app_empty(None, app_id)

def startModules():
	novell = Novell()
	novell.perform()
