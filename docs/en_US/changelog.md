# Changelog

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
