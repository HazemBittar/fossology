###############################################################################
# SPDX-License-Identifier: GPL-2.0-only
# SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
###############################################################################

NAME=scheduler
VERSION=VERSION
CURRDIR=.

all: install

install:

	@echo "TEST: installing $(NAME)"
	mkdir -p $(DESTDIR)$(MODDIR)/$(NAME)/agent
	install $(CURRDIR)/agent/fo_scheduler $(DESTDIR)$(MODDIR)/$(NAME)/agent/fo_scheduler
	install $(CURRDIR)/agent/fo_scheduler $(DESTDIR)$(MODDIR)/$(NAME)/agent/fo_cli
	install -m 644 $(CURRDIR)/$(VERSION) $(DESTDIR)$(MODDIR)/$(NAME)/VERSION
	mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	ln -s $(MODDIR)/$(NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled

uninstall:

	@echo "TEST: uninstalling $(NAME)"
	rm -rf $(DESTDIR)$(MODDIR)/$(NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(NAME)

clean:

.PHONY: all test coverage install uninstall clean
