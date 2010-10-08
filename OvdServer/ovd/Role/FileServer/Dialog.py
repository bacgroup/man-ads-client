# -*- coding: utf-8 -*-

# Copyright (C) 2008-2009 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2008
# Author Laurent CLOUET <laurent@ulteo.com> 2009
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

import httplib
import urllib
import urllib2
import socket
from xml.dom import minidom
from xml.dom.minidom import Document

from ovd.Communication.Dialog import Dialog as AbstractDialog
from ovd.Logger import Logger
from ovd import util

from Config import Config
from Share import Share
from User import User


class Dialog(AbstractDialog):
	def __init__(self, role_instance):
		self.role_instance = role_instance
	
	@staticmethod
	def getName():
		return "fs"
	
	
	def process(self, request):
		path = request["path"]
		
		if request["method"] == "GET":
			Logger.debug("do_GET "+path)
			
			if path == "/shares":
				return self.req_list_all(request)
		
			elif path == "/statistics":
				return self.req_statistics(request)
		
		elif request["method"] == "POST":
			if path == "/share/create":
				return self.req_share_create(request)
			
			elif path == "/share/delete":
				return self.req_share_delete(request)
			
			elif path == "/access/enable":
				return self.req_enable_user(request)
			
			elif path == "/access/disable":
				return self.req_disable_user(request)
		
		return None
	
	
	def req_list_all(self, request):
		shares = self.role_instance.get_existing_shares()
		infos  = self.role_instance.get_disk_size_infos()
		
		doc = Document()
		rootNode = doc.createElement('shares')
		rootNode.setAttribute("total_size", str(infos[0]))
		rootNode.setAttribute("free_size", str(infos[1]))
		doc.appendChild(rootNode)
		
		for share in shares:
			node = doc.createElement('share')
			node.setAttribute("id", share.name)
			node.setAttribute("status", str(share.status()))
			rootNode.appendChild(node)
		
		return self.req_answer(doc)
	
	
	def req_statistics(self, request):
		infos  = self.role_instance.get_disk_size_infos()
		
		doc = Document()
		rootNode = doc.createElement('statistics')
		sizeNode = doc.createElement('size')
		sizeNode.setAttribute("total", str(infos[0]))
		sizeNode.setAttribute("free", str(infos[1]))
		rootNode.appendChild(sizeNode)
		doc.appendChild(rootNode)
		
		return self.req_answer(doc)
	
	
	def req_share_create(self, request):
		try:
			document = minidom.parseString(request["data"])
			roodNode = document.documentElement
			if roodNode.nodeName != "share":
				raise Exception("invalid root node")
			
			share_id = roodNode.getAttribute("id")
			if len(share_id)==0 or "/" in share_id:
				raise Exception("invalid root node")
		
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		
		share = Share(share_id, Config.spool)
		if self.role_instance.shares.has_key(share_id) or share.status() is not Share.STATUS_NOT_EXISTS:
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "already_exists")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		if not share.create():
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "system_error")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		
		self.role_instance.shares[share_id] = share
		return self.share2xml(share)
	
	
	def req_share_delete(self, request):
		try:
			document = minidom.parseString(request["data"])
			roodNode = document.documentElement
			if roodNode.nodeName != "share":
				raise Exception("invalid root node")
			
			share_id = roodNode.getAttribute("id")
			if len(share_id)==0 or "/" in share_id:
				raise Exception("invalid root node")
		
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		if self.role_instance.shares.has_key(share_id):
			share = self.role_instance.shares[share_id]
			del(self.role_instance.shares[share_id])
		else:
			share = Share(share_id, Config.spool)
			share.delete()
		
		return self.share2xml(share)
	
	
	def share2xml(self, share):
		doc = Document()
		rootNode = doc.createElement('share')
		rootNode.setAttribute("id", share.name)
		rootNode.setAttribute("status", str(share.status()))
		doc.appendChild(rootNode)
		return self.req_answer(doc)
	
	def user2xml(self, user, exists):
		doc = Document()
		rootNode = doc.createElement('user')
		rootNode.setAttribute("login", user)
		if exists:
			status = "ok"
		else:
			status = "unknown"
		rootNode.setAttribute("status", status)
		doc.appendChild(rootNode)
		return self.req_answer(doc)
	
	
	def req_enable_user(self, request):
		try:
			document = minidom.parseString(request["data"])
			
			rootNode = document.documentElement
			if rootNode.nodeName != "session":
				raise Exception("invalid root node")
			
			user = rootNode.getAttribute("login")
			password = rootNode.getAttribute("password")
			
			shares = []
			for node in rootNode.getElementsByTagName("share"):
				shares.append(node.getAttribute("id"))
		
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		u = User(user)
		if u.existSomeWhere():
			Logger.warn("FS: Enable user %s but already exists in system: purging it"%(user))
			u.clean()
			
			if u.existSomeWhere():
				Logger.error("FS: unable to del user %s"%(user))
				doc = Document()
				rootNode = doc.createElement('error')
				rootNode.setAttribute("id", "system_error")
				rootNode.setAttribute("msg", "user already exists and cannot be deleted")
				doc.appendChild(rootNode)
				return self.req_answer(doc)
		
		if not u.create(password):
			Logger.error("FS: unable to create user %s"%(user))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "system_error")
			rootNode.setAttribute("msg", "user cannot be created")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		somethingWrong = False
		for share_id in shares:
			if not self.role_instance.shares.has_key(share_id):
				somethingWrong = True
				response = self.share2xml(Share(share_id, Config.spool))
				break
			
			share = self.role_instance.shares[share_id]
			if not share.add_user(user):
				somethingWrong = True
				doc = Document()
				rootNode = doc.createElement('error')
				rootNode.setAttribute("id", "system_error")
				rootNode.setAttribute("msg", "share cannot enable user")
				doc.appendChild(rootNode)
				response = self.req_answer(doc)
				break
		
		if somethingWrong:
			for share_id in shares:
				try:
					share = self.role_instance.shares[share_id]
				except:
					continue
				
				share.del_user(user)
			u.destroy()
			
			return response
		
		Logger.debug("FS:request req_enable_user return success")
		return self.req_answer(document)
	
	
	
	def req_disable_user(self, request):
		try:
			document = minidom.parseString(request["data"])
			
			rootNode = document.documentElement
			if rootNode.nodeName != "session":
				raise Exception("invalid root node")
			
			user = rootNode.getAttribute("login")
			
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		
		u = User(user)
		if not u.existSomeWhere():
			Logger.warn("FS: Cannot disable unknown user %s"%(user))
			return self.user2xml(user, False)
		
		somethingWrong = False
		
		for share in self.role_instance.shares.values():
			if share.has_user(user):
				if not share.del_user(user):
					somethingWrong = True
		
		if not u.destroy():
			somethingWrong = True
		
		if somethingWrong:
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "system_error")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		Logger.debug("FS:request req_disable_user return success")
		return self.req_answer(document)
