#! /usr/bin/env python

# Copyright (C) 2010-2011 Ulteo SAS
# http://www.ulteo.com
# Author Samuel BOVEE <samuel@ulteo.com> 2010
# Author Julien LANGLOIS <julien@ulteo.com> 2011
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

import ConfigParser
import getopt
import os
import re
import sys

def add_role(filename, role):
	parser = parse(filename)
	
	roles = get_roles(parser)
	if role in roles:
		return True
	
	roles.append(role)
	
	# No using configparser write function because does 
	# not kep file structure, blanks, comments ...
	f = file(filename, "r")
	content = f.read()
	f.close()
	
	pattern = re.compile("^roles *= *(.*)", re.MULTILINE)
	content2 = pattern.sub("roles = %s"%(" ".join(roles)), content)
	
	if content == content2:
		# no roles items existing in conf
		pos = content2.find("[main]")
		if pos == -1:
			print >> sys.stderr, "invalid configuration file, missing [main] part"
			sys.exit(2)
		
		pos+= len("[main]")
		content2 = content2[:pos] + "\nroles = %s"%(" ".join(roles)) + content2[pos:]
	
	
	f = file(filename, "w")
	f.write(content2)
	f.close()


def del_role(filename, role):
	parser = parse(filename)
	
	roles = get_roles(parser)
	if role not in roles:
		return True
	
	roles.remove(role)
	
	# No using configparser write function because does 
	# not kep file structure, blanks, comments ...
	f = file(filename, "r")
	content = f.read()
	f.close()
	
	pattern = re.compile("^roles *= *(.*)", re.MULTILINE)
	content2 = pattern.sub("roles = %s"%(" ".join(roles)), content)
	
	f = file(filename, "w")
	f.write(content2)
	f.close()


def ls_role(filename):
	parser = parse(filename)
	
	roles = get_roles(parser)
	
	print " ".join(roles)


def parse(filename):
	parser = ConfigParser.ConfigParser()
	try:
		parser.read(filename)
	except Exception, err:
		print >> sys.stderr, "invalid configuration file '%s'"%(filename)
		sys.exit(2)
	
	return parser


def get_roles(parser):
	roles = []
	
	if parser.has_option("main", "roles"):
		buf = parser.get("main", "roles").split(' ')
		for b in buf:
			b = b.strip()
			if len(b)==0:
				continue
			
			roles.append(b)
		
	return roles


def usage():
	print >> sys.stderr, "Usage: %s [-m <conffile>] <add|del> <role>"%(sys.argv[0])
	print >> sys.stderr, "       %s [-m <conffile>] ls"%(sys.argv[0])


if __name__ == "__main__":
	CONFFILE = "/etc/ulteo/ovd/slaveserver.conf"
	
	try:
		opts, args = getopt.getopt(sys.argv[1:], "m:", [])
	
	except getopt.GetoptError, err:
		print >> sys.stderr, str(err)
		usage()
		sys.exit(127)
	
	for o, a in opts:
		if o == "-m":
			CONFFILE = a
	
	if len(args) < 1 or len(args) > 2:
		usage()
		sys.exit(127)
	
	if not os.path.exists(CONFFILE):
		print >> sys.stderr, "Configuration file '%s' not found"%(CONFFILE)
		sys.exit(1)
	
	if args[0] in ["add", "del"] and len(args)!=2:
		usage()
		sys.exit(127)
	
	if args[0] == "add":
	  add_role(CONFFILE, args[1])
	
	elif  args[0] == "del":
		del_role(CONFFILE, args[1])
	
	elif  args[0] == "ls":
		ls_role(CONFFILE)
	
	else:
		usage()
		sys.exit(1)
