import 'package:flutter/material.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';

/// Consistent surface for grouped content: rounded corners, a soft shadow and a
/// themed background. Use wherever a "card" is needed so every surface matches.
class CardWidget extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final double radius;
  final VoidCallback? onTap;
  final Color? color;
  final bool border;
  final bool shadow;
  const CardWidget({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(Dimensions.paddingSizeDefault),
    this.margin,
    this.radius = Dimensions.radiusLarge,
    this.onTap,
    this.color,
    this.border = false,
    this.shadow = true,
  });

  @override
  Widget build(BuildContext context) {
    final BorderRadius br = BorderRadius.circular(radius);
    final Widget content = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: color ?? Theme.of(context).cardColor,
        borderRadius: br,
        border: border ? Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.15)) : null,
        boxShadow: shadow
            ? [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 12, offset: const Offset(0, 4))]
            : null,
      ),
      child: child,
    );
    if (onTap == null) return Container(margin: margin, child: content);
    return Container(
      margin: margin,
      child: Material(
        color: Colors.transparent,
        borderRadius: br,
        child: InkWell(borderRadius: br, onTap: onTap, child: content),
      ),
    );
  }
}
