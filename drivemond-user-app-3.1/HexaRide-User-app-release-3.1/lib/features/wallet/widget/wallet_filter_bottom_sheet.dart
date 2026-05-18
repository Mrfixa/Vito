import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/calender_widget.dart';
import 'package:ride_sharing_user_app/features/wallet/controllers/wallet_controller.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class WalletFilterBottomSheet extends StatelessWidget {
  const WalletFilterBottomSheet({super.key});

  WalletController get _walletController => Get.find<WalletController>();

  void _openCalendar(BuildContext context) {
    showDialog(
      context: context,
      barrierColor: Colors.black54,
      builder: (_) => Dialog(
        insetPadding: EdgeInsets.zero,
        backgroundColor: Colors.transparent,
        child: CalenderWidget(
          onChanged: (_) {},
          onApply: (start, end) => _walletController.setCustomDateRange(start, end),
        ),
      ),
    );
  }

  void _apply() {
    _walletController.getTransactionList(1);
    Get.back();
  }

  @override
  Widget build(BuildContext context) {
    return GetBuilder<WalletController>(
      builder: (walletController) {
        final isCustomSelected = walletController.selectedDurationIndex == walletController.durationFilterList.length - 1;
        return Container(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(Dimensions.paddingSizeLarge)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _DragHandle(),
              _SheetHeader(onClose: Get.back),

              Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('duration'.tr,
                        style: textMedium.copyWith(
                            fontSize: Dimensions.fontSizeDefault)),
                    const SizedBox(height: Dimensions.paddingSizeSmall),

                    _DurationDropdown(
                      selected: walletController.selectedDurationIndex,
                      items: walletController.durationFilterList,
                      isCustomSelected: isCustomSelected,
                      customDateStart: walletController.customDateStart,
                      customDateEnd: walletController.customDateEnd,
                      onChanged: (value) {
                        walletController.setDurationFilter(value);
                        if (value == walletController.durationFilterList.length - 1) {
                          _openCalendar(context);
                        }
                      },
                      onCalendarTap: () => _openCalendar(context),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    Text('transaction_type'.tr,
                        style: textMedium.copyWith(
                            fontSize: Dimensions.fontSizeDefault)),
                    const SizedBox(height: Dimensions.paddingSizeSmall),

                    _TransactionTypeSelector(
                      selected: walletController.selectedTransactionTypeIndex,
                      onChanged: walletController.setTransactionTypeFilter,
                    ),
                    const SizedBox(height: Dimensions.paddingSizeLarge),

                    _ActionButtons(
                      onReset: walletController.resetFilters,
                      onApply: _apply,
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}


class _DragHandle extends StatelessWidget {
  const _DragHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
        height: 6,
        width: 30,
        decoration: BoxDecoration(
          color: Theme.of(context).hintColor.withValues(alpha: 0.3),
          borderRadius: BorderRadius.circular(100),
        ),
      ),
    );
  }
}


class _SheetHeader extends StatelessWidget {
  const _SheetHeader({required this.onClose});

  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Dimensions.paddingSizeDefault,
        Dimensions.paddingSizeDefault,
        Dimensions.paddingSizeDefault,
        Dimensions.paddingSizeSmall,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const SizedBox(width: 32),
          Text('filter_by'.tr, style: textSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
          InkWell(
            onTap: onClose,
            borderRadius: BorderRadius.circular(100),
            child: Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: Theme.of(context).hintColor.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.close, size: 18, color: Theme.of(context).textTheme.bodyMedium?.color),
            ),
          ),
        ],
      ),
    );
  }
}


class _DurationDropdown extends StatelessWidget {
  const _DurationDropdown({
    required this.selected,
    required this.items,
    required this.onChanged,
    required this.isCustomSelected,
    required this.onCalendarTap,
    this.customDateStart,
    this.customDateEnd,
  });

  final int selected;
  final List<String> items;
  final ValueChanged<int> onChanged;
  final bool isCustomSelected;
  final VoidCallback onCalendarTap;
  final String? customDateStart;
  final String? customDateEnd;

  bool get _hasRange => customDateStart != null && customDateEnd != null;

  String _customLabel(BuildContext context) => _hasRange ? '$customDateStart  →  $customDateEnd' : 'select_your_date'.tr;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSize),
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: selected,
          isExpanded: true,
          icon: isCustomSelected ? InkWell(
            onTap: onCalendarTap,
            borderRadius: BorderRadius.circular(4),
            child: Icon(Icons.edit_calendar_outlined,
                size: 18,
                color: Theme.of(context).primaryColor),
          ) :
          Icon(Icons.arrow_drop_down, color: Theme.of(context).disabledColor), items: items.asMap().entries.map((e) =>
            DropdownMenuItem<int>(
            value: e.key,
            child: Text(e.value.tr, style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).textTheme.bodyMedium?.color)),
          )).toList(),
          selectedItemBuilder: (context) => items.asMap().entries.map((e) {
            final isCustomItem = e.key == items.length - 1;
            return Align(
              alignment: Alignment.centerLeft,
              child: Text(
                isCustomItem && isCustomSelected ? _customLabel(context) : e.value.tr,
                style: textRegular.copyWith(
                  fontSize: Dimensions.fontSizeDefault,
                  color: isCustomItem && isCustomSelected ? Theme.of(context).primaryColor : Theme.of(context).textTheme.bodyMedium?.color,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            );
          }).toList(),
          onChanged: (v) {
            if (v != null) onChanged(v);
          },
        ),
      ),
    );
  }
}


class _TransactionTypeSelector extends StatelessWidget {
  const _TransactionTypeSelector({
    required this.selected,
    required this.onChanged,
  });

  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeSmall,
        vertical: Dimensions.paddingSizeDefault,
      ),
      decoration: BoxDecoration(
        border: Border.all(
          color: Theme.of(context).hintColor.withValues(alpha: 0.3),
        ),
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
      ),
      child: Row(
        children: [
          _RadioOption(value: 0, label: 'both'.tr,   selected: selected, onTap: onChanged),
          _RadioOption(value: 1, label: 'debit'.tr,  selected: selected, onTap: onChanged),
          _RadioOption(value: 2, label: 'credit'.tr, selected: selected, onTap: onChanged),
        ],
      ),
    );
  }
}


class _RadioOption extends StatelessWidget {
  const _RadioOption({
    required this.value,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final int value;
  final String label;
  final int selected;
  final ValueChanged<int> onTap;

  bool get _isSelected => selected == value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: () => onTap(value),
        borderRadius:
        BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
        child: Row(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeExtraSmall),
              child: Container(
                width: 20,
                height: 20,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: _isSelected ? Theme.of(context).primaryColor : Theme.of(context).hintColor.withValues(alpha: 0.5),
                    width: 2,
                  ),
                ),
                child: _isSelected
                    ? Center(
                  child: Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Theme.of(context).primaryColor,
                    ),
                  ),
                ) : null,
              ),
            ),
            Text(
              label,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: _isSelected ? Theme.of(context).primaryColor : Theme.of(context).textTheme.bodyMedium?.color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}


class _ActionButtons extends StatelessWidget {
  const _ActionButtons({
    required this.onReset,
    required this.onApply,
  });

  final VoidCallback onReset;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton(
            onPressed: onReset,
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeDefault),
              shape: RoundedRectangleBorder(
                borderRadius:
                BorderRadius.circular(Dimensions.paddingSizeSmall),
              ),
              side: BorderSide(
                color: Theme.of(context).hintColor.withValues(alpha: 0.3),
              ),
            ),
            child: Text('reset'.tr,
                style: textMedium.copyWith(
                  color: Theme.of(context).textTheme.bodyMedium?.color,
                  fontSize: Dimensions.fontSizeDefault,
                )),
          ),
        ),
        const SizedBox(width: Dimensions.paddingSizeDefault),

        Expanded(
          child: ElevatedButton(
            onPressed: onApply,
            style: ElevatedButton.styleFrom(
              backgroundColor: Theme.of(context).primaryColor,
              padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeDefault),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
              ),
            ),
            child: Text('filter'.tr,
                style: textMedium.copyWith(
                  color: Colors.white,
                  fontSize: Dimensions.fontSizeDefault,
                )),
          ),
        ),
      ],
    );
  }
}