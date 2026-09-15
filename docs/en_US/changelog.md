# Changelog

## 0.2

- One tile on the dashboard instead of nineteen stacked widgets: instant power,
  a gauge against rated power, daily and total energy, temperature, grid
  voltage, status — and the per-string detail as soon as there is more than one
  string. Three layouts, one per device family.
- Detail commands are now hidden when created. Those of already-created devices
  become hidden once, on update: show back whatever you want in the device's
  Commands tab.
- The inverter's rated power is read from the stick on its own and scales the
  gauge. It stays editable by hand.
- Three widget options: gauge, secondary figures, per-string detail.

## 0.1

First release.

- Discovery of the devices behind the AiSWEI stick's IP address: inverters,
  meter, battery.
- One Jeedom device per physical device, with historized and properly scaled
  info commands.
- Inverter: power, daily and total energy, voltage and current per phase and per
  PV string, computed per-string power, temperature, frequency, power factor,
  operating hours, status and plain-language error label.
- Meter: signed grid power and its two halves, import and export indexes.
- Battery: state of charge and of health, power, voltage, current, temperature,
  energies, backup supply.
- Cron-driven polling, per-device interval from one to thirty minutes.
- Night handling: powers optionally reset to zero, energy indexes never touched,
  attempts spaced out and log kept quiet.
- Diagnostics tab showing the stick's raw answer.
