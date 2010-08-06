/*
 * Copyright (C) 2009 Ulteo SAS
 * http://www.ulteo.com
 * Author Julien LANGLOIS <julien@ulteo.com> 2010
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

package org.ulteo.ovd.client.session;

public class Properties {
	public static final int MODE_ANY = 0;
	public static final int MODE_REMOTEAPPS = 1;
	public static final int MODE_DESKTOP = 2;
	
	private int mode = 0;
	private String lang = null;
	
	private boolean multimedia = false;
	private boolean printers = false;
	
	public Properties(int mode) {
		this.mode = mode;
	}
	
	public int getMode() {
		return this.mode;
	}
	
	public void setMode(int mode) {
		this.mode = mode;
	}
	
	public String getLang() {
		return lang;
	}
	
	public void setLang(String lang) {
		this.lang = lang;
	}
	
	public boolean isMultimedia() {
		return multimedia;
	}
	
	public void setMultimedia(boolean multimedia) {
		this.multimedia = multimedia;
	}
	
	public boolean isPrinters() {
		return printers;
	}
	
	public void setPrinters(boolean printers) {
		this.printers = printers;
	}
}
